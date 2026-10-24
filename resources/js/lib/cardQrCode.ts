import QRCode from 'qrcode';

export interface CardQrCode {
    id: number;
    card_id: number;
    label: string | null;
    token: string;
    is_active: boolean;
    expires_at: string | null;
    scans_count: number;
    last_scanned_at: string | null;
    created_at: string;
}

export type QrExpiryOption = 'never' | 'date';

/*
 * Mismas opciones que los QR de boletos (ticket-orders/Show.vue).
 */
export const qrDataUrl = (token: string, width: number): Promise<string> =>
    QRCode.toDataURL(token, {
        width,
        margin: 2,
        errorCorrectionLevel: 'H',
    });

/**
 * Descargar el QR en PNG con resolución para impresión.
 */
export const downloadQrPng = async (
    token: string,
    filename: string,
): Promise<void> => {
    const link = document.createElement('a');

    link.href = await qrDataUrl(token, 1024);
    link.download = `${filename}.png`;
    link.click();
};

/**
 * Nombre de archivo: qr-{tarjeta}-{id}.png
 */
export const qrFilename = (cardName: string, qrCodeId: number): string => {
    const slug = cardName
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');

    return `qr-${slug || 'tarjeta'}-${qrCodeId}`;
};

export const isQrExpired = (qrCode: Pick<CardQrCode, 'expires_at'>): boolean =>
    qrCode.expires_at !== null && new Date(qrCode.expires_at) <= new Date();

export const formatQrDate = (value: string | null): string =>
    value
        ? new Intl.DateTimeFormat('es-MX', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

/**
 * Valor para <input type="datetime-local"> en hora local.
 */
export const toDateTimeLocal = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
};
