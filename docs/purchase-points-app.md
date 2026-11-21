# Puntos por comprar boletos y donar: guía para la app móvil

> **Estado (2026-11-20):** el servidor está listo; falta la app.
> Instrucciones para implementar el cliente en `../zooApp` (ZooDeck, Expo + ViroReact).
>
> Esta guía cubre boletos y donaciones. Cómo funcionan los puntos en general, las temporadas,
> `GET /api/points/season` y el catálogo de íconos y colores están en
> [points-app.md](points-app.md). Los puntos por capturar tarjetas, en
> [card-points-app.md](card-points-app.md): `data.points` tiene **el mismo formato** en las
> tres acciones, así que la app puede reutilizar el tipo y el componente de la victoria.
>
> Para quien implemente esto en `zooApp`: sigue el `AGENTS.md` de ese repositorio
> (estilo, componentes, dónde van los hooks de datos, commits). Ahí manda ese archivo
> sobre este.

## Qué hace el servidor

Al **confirmarse el pago** de una compra de boletos o de una donación, el servidor le suma al
usuario los puntos de la regla de esa acción, dentro de la misma transacción, y los devuelve en
la respuesta. Hoy el pago se confirma en la misma petición; cuando se integre el proveedor de
pagos, el servidor otorgará los puntos al confirmarse el pago, sin darlos dos veces.

| Acción | Endpoint | Regla (`point_rules.type`) | Cuándo da puntos |
| --- | --- | --- | --- |
| Comprar boletos | `POST /api/tickets/orders` | `ticket_purchase` | Una vez por orden, sin importar cuántos boletos tenga |
| Donar | `POST /api/donations` | `donation` | Una vez por donación, solo si el monto es de **al menos 50 MXN** |

- Los administradores cambian los puntos o desactivan cada regla en "Reglas de puntos". El
  mínimo de la donación se configura en el servidor (`POINTS_DONATION_MIN_AMOUNT`).
  **La app nunca debe calcular ni guardar en código cuántos puntos da una compra o donación,
  ni el mínimo**: siempre usa lo que devuelve el servidor.
- Los puntos nunca impiden la acción: con la regla desactivada, puesta en 0 por la temporada o
  una donación bajo el mínimo, la compra o donación se completa igual (`201`) y
  `points.awarded` llega en `0`.
- Las mismas reglas se aplican en taquilla (venta con el QR del usuario) y en las donaciones que
  registra el panel a nombre del usuario. Esas no pasan por la app; sus puntos se ven en
  `GET /api/profile`.
- Todo es autoritativo del servidor: no hay endpoint para sumar puntos desde la app.

## Contrato: `data.points`

Es un campo **nuevo y aditivo** en las dos respuestas; el resto de cada respuesta no cambió.
Mismo formato que `data.points` al ganar un duelo.

### `POST /api/tickets/orders` (HTTP 201)

Esta respuesta no trae `success` (nunca lo trajo); `points` va dentro de `data`, junto a
`tickets`.

```jsonc
{
  "message": "Compra realizada correctamente.",
  "data": {
    "id": 1,
    "folio": "ZOO-20261120-64707",
    "source": "app",
    "status": "paid",
    "subtotal": "240.00",
    "discount": "0.00",
    "total": "240.00",
    "paid_at": "2026-11-20T06:37:08.000000Z",
    "payment": { "method": "Tarjeta", "reference": "APP-SIMULATED-G7CE00OES52I" },
    "items": [ /* igual que antes */ ],
    "tickets": [ /* igual que antes */ ],
    "points": {
      "awarded": 20,            // puntos de esta compra (0 si la regla no da puntos)
      "total": 310,             // total acumulado del usuario DESPUÉS de la compra
      "level": { "id": 2, "name": "Plata" },   // nivel después; null si no hay niveles
      "level_up": true,         // true si esta compra lo subió de nivel
      "season": {               // null si no hay modo activo o si está oculto
        "title": "Fin de semana doble",
        "message": "Comprar boletos da el doble de puntos.",   // puede ser null
        "base_points": 10,      // puntos de la compra sin temporada; null si no da puntos
        "icon": "pumpkin",      // clave del catálogo; null = predeterminado
        "color": { "accent": "#B24E0A", "on_accent": "#FFFFFF", "soft": "#FDEBDC", "text": "#6B2E05" }
      }
    }
  }
}
```

### `POST /api/donations` (HTTP 201)

```jsonc
{
  "success": true,
  "message": "Donación registrada correctamente.",
  "data": {
    "donation": { /* igual que antes */ },
    "points": {
      "awarded": 5,
      "total": 315,
      "level": { "id": 2, "name": "Plata" },
      "level_up": false,
      "season": null
    }
  }
}

// Donación de 20 MXN (bajo el mínimo) con una temporada visible:
"points": {
  "awarded": 0,
  "total": 315,
  "level": { "id": 2, "name": "Plata" },
  "level_up": false,
  "season": { "title": "Fin de semana doble", "message": "…", "base_points": null, "icon": "pumpkin", "color": { /* … */ } }
}
```

`points` siempre es un objeto en estas dos respuestas. Trátalo como opcional (`?? null`) por si
la app habla con un servidor anterior.

### Cómo leer `awarded` contra `base_points`

Igual que en el duelo (todos los casos tienen prueba en
`tests/Feature/Api/PaymentPointsTest.php`):

| Caso | `awarded` | `base_points` | Qué mostrar |
| --- | --- | --- | --- |
| Sin temporada (o modo oculto) | puntos reales | — (`season: null`) | "+{awarded} puntos" |
| La temporada sube los puntos | `20` | `10` | "+20" y el aviso: "Por la temporada {title}, esta compra da 20 puntos en lugar de 10" |
| La temporada los baja | `3` | `10` | "+3" y el mismo aviso |
| La temporada los pone en 0 | `0` | `10` | Sin "+0"; el aviso: "… da 0 puntos en lugar de 10" |
| La temporada no cambia esta acción | `10` | `10` | "+10" y solo el chip de temporada, sin comparación |
| Regla desactivada, o donación bajo el mínimo | `0` | `null` | Nada de puntos; solo el chip si hay temporada |

## Implementación en la app (checklist)

1. **Tipos:** reutiliza el tipo de los puntos del duelo (`DuelPoints` con `season`, en
   `src/types/cards.ts`); conviene renombrarlo a algo general (por ejemplo `PointsAward` en
   `src/types/points.ts`). Agrega `points?: PointsAward | null` a la respuesta de la compra y a
   la de la donación.
2. **Pantalla de éxito** (compra y donación): si `points` existe y `points.awarded > 0`, muestra
   "+{awarded} puntos" y "Total: {total} puntos". Con `awarded === 0` no muestres "+0". Si
   `points.level_up`, muestra el aviso de nivel nuevo con `points.level.name`, sin tapar los
   boletos ni el comprobante. Reutiliza el componente de la victoria del duelo
   (`src/components/duel/DuelPoints.tsx`) si encaja.
3. **Aviso de temporada:** igual que en la victoria del duelo. Si `points.season` existe, chip
   con el ícono (círculo `accent`/`on_accent`) y `season.title`. Si
   `base_points !== null && base_points !== awarded`, muestra
   "Por la temporada {title}, esta compra (o donación) da {awarded} puntos en lugar de
   {base_points}" y, debajo, `season.message` si no es `null`. Fondo `soft`, texto `text`.
4. **Perfil:** después de mostrar los puntos, **revalida `GET /api/profile`** (puntos, nivel,
   siguiente nivel y progreso). No sumes a mano.
5. **Donación:** no anuncies el mínimo ni prometas puntos antes de donar; el servidor decide
   (el mínimo puede cambiar). Con `awarded === 0` la donación fue exitosa igual: agradece sin
   hablar de puntos.
6. **Textos:** en español, como el resto de la app. Usa `level.name`, `title` y `message` tal
   cual vienen.
7. **Sin conexión:** los puntos solo existen en la respuesta del servidor. Si la petición falla
   por red, no muestres puntos supuestos: revalida `GET /api/profile` y la lista de boletos o
   donaciones para saber si la acción se registró.

## Cómo probar

- Compra 1 boleto y luego 3 en una sola orden: cada orden da los puntos de "Compra de boleto"
  una vez (10 por defecto), no por boleto.
- Dona 100 MXN: da los puntos de "Donación" (5 por defecto). Dona 20 MXN: la donación se
  registra y `awarded` llega en `0`, sin "+0".
- Desactiva "Compra de boleto" en "Reglas de puntos" y compra: la compra se completa, sin puntos.
- Pon a un usuario cerca del mínimo del siguiente nivel (por ejemplo, 295 puntos con Plata en
  300) y compra: sale el aviso de nivel nuevo.
- Temporada: en "Modos de puntos" crea un modo visible con la compra de boleto a 20 y la
  donación a 0, y actívalo. Comprar da `awarded: 20` con `season.base_points: 10` y el aviso;
  donar 100 da `awarded: 0` con `base_points: 5` ("0 en lugar de 5"). Apaga "Mostrar a los
  visitantes": `season` llega en `null`, pero los puntos siguen siendo los del modo. Vuelve a
  los puntos base al terminar.
- En el panel, "Movimientos de puntos" muestra cada compra como "Compra de boleto" y cada
  donación como "Donación", con la orden o donación como referencia.
