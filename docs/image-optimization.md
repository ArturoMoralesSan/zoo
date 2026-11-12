# Optimización de imágenes de tarjetas (WebP)

Las imágenes de las tarjetas (`generated_image`, `thumbnail_image`, `artwork_image`) se
guardan en **WebP**. La app las descarga por `card_image_url` y `thumbnail_image`, y Viro
usa `card_image_url` como textura de la tarjeta 3D.

## Por qué WebP y no AVIF

| | WebP | AVIF |
| --- | --- | --- |
| `<Image>` de React Native en Android (Fresco) | sí | **no** |
| Texturas de Viro (Fresco en Android, UIImage en iOS) | sí (iOS 14+) | **no** en Android, iOS 16+ |
| expo-image | sí | sí |
| Codificar en el navegador | WASM (libwebp) en todos | ningún navegador codifica AVIF desde un canvas |
| Codificar en el servidor | GD e Imagick suelen tenerlo | requiere libavif/libheif |

AVIF rompería las texturas de Viro en Android, y solo pesa ~20-30 % menos que WebP.

## Perfiles

Compartidos entre PHP y TS en `resources/data/card-image-profiles.json`. Nunca se agranda
una imagen.

| Imagen | Máximo | Calidad | Motivo |
| --- | --- | --- | --- |
| `generated` | 1144 × 2032 | 88 | 2× la plantilla, igual que la textura del visor 3D |
| `thumbnail` | 800 × 800 | 82 | cuadrícula de la app |
| `artwork` | 2048 × 2048 | 90 | alimenta futuros renders de la tarjeta |

Medido el 2026-11-10 con una tarjeta real: PNG de 1716 × 3048 y **5,98 MB → 215-226 KB**,
sin diferencia visible a 100 % en texto, iconos ni esquinas transparentes. La VRAM de la
textura baja de ~21 MB a ~9 MB. El PNG a 3× sigue disponible con el botón de descarga del
editor.

## Quién codifica

1. **Navegador** (`lib/imageOptimize.ts`): libwebp en WASM (`@jsquash/webp`) con
   `use_sharp_yuv` y `method: 4` (~0,7 s por tarjeta; `method: 6` pesa ~5 % menos pero
   tarda ~6 s). Si el WASM no carga, usa `canvas.toBlob('image/webp')`. Si el navegador
   no produce WebP (Safari antiguo), envía el archivo original o un PNG a 2×.
2. **Servidor**, solo si puede (`App\Services\ImageEncoding`): Imagick con sharp YUV o, si
   no, GD (sin sharp YUV, el texto queda un poco más suave). Se elige con
   `IMAGE_ENCODER=auto|imagick|gd|none`. Al guardar, convierte los archivos que no llegaron
   en WebP o exceden su perfil. Si falla, guarda el original y escribe un `warning` en el
   log. Guardar nunca falla por la compresión.

Instalación en Debian/Ubuntu: `sudo apt install php8.4-imagick php8.4-gd` (o los
paquetes de tu versión de PHP), y luego comprobar:

```bash
php -r 'var_dump(in_array("WEBP", Imagick::queryFormats("WEBP")));'
php artisan cards:images:report   # muestra el codificador detectado
```

## Peso en el panel

Las tarjetas guardan en `cards.image_original_bytes` (`{ generated, thumbnail,
artwork }`, en bytes) el peso de cada imagen antes de comprimirla, porque los
originales no se conservan. El navegador envía `image_original_bytes[...]` al
guardar: el archivo elegido para la miniatura y la ilustración, y el mismo render
en PNG para la tarjeta generada. Sin ese dato, el servidor usa el peso del archivo
recibido. La herramienta de conversión registra el peso del original que reemplaza.
Al eliminar una imagen se olvida su peso.

`CardController::fileSizes()` entrega a Show y Edit `fileSizes.{generated,
thumbnail, artwork, model}` con `{ current, original }`. El componente
`SizeSavings.vue` muestra «original → en la app −N %». En los formularios, el peso
de una imagen nueva se calcula al elegirla; `optimizeUpload` guarda el resultado
para no codificarla dos veces al enviar.

## Convertir las imágenes ya guardadas

**Cada original se borra** en cuanto su WebP queda guardado. No hay forma de deshacerlo,
así que antes de convertir copia `storage/app/public/cards`.

- **Panel:** Tarjetas → Configuración → "Optimizar imágenes". Procesa una imagen a la vez.
  Si el servidor tiene codificador, codifica él; si no, el navegador descarga, codifica y
  sube cada imagen.
- **Consola** (requiere Imagick o GD):

```bash
php artisan cards:images:report                  # solo lectura
php artisan cards:images:optimize --dry-run      # qué se convertiría
php artisan cards:images:optimize [--card=ID] [--image=generated|thumbnail|artwork] [--limit=N]
```

Ambos usan `CardImageOptimizer::replace()`, que:

1. Rechaza con 409 si la ruta guardada ya no es la que se optimizó (edición concurrente).
2. Rechaza con 422 si el resultado no es WebP, excede el perfil o **no pesa menos** que
   el original. Esa imagen queda pendiente, sin cambios.
3. Guarda el WebP y actualiza la columna dentro de una transacción con `lockForUpdate`.
4. Borra el original solo después del commit. Si algo falla, borra el WebP nuevo.

## Hosting

- El navegador necesita los `.wasm` de `public/build/assets`. Si el servidor no los envía
  como `application/wasm`, Emscripten recurre a una carga más lenta, pero funciona igual.
- Imagick tarda ~7 s por tarjeta a 3×; GD ~1 s. Ambos caben en el `max_execution_time`
  habitual, porque el panel procesa una imagen por petición.
