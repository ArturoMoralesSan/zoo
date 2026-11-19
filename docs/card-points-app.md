# Puntos por capturar tarjetas: guía para la app móvil

> **Estado:** el servidor ya está implementado (2026-11-17); la parte de la app todavía no.
> Instrucciones para implementar el cliente en `../zooApp` (ZooDeck, Expo + ViroReact).
>
> Para quien implemente esto en `zooApp`: sigue el `AGENTS.md` de ese repositorio
> (estilo, componentes, dónde van los hooks de datos, commits). Ahí manda ese archivo
> sobre este.

## Qué hace el servidor

Al **ganar un duelo** (`POST /api/duels/{attempt}/submit` con `outcome: "won"`), el
servidor captura la tarjeta y le suma al usuario los puntos de la **regla de puntos que
el panel tiene elegida para la rareza** de esa tarjeta. Todo pasa en la misma transacción
que la captura.

Cada rareza apunta a una regla de puntos (o a ninguna) en "Tarjetas → Configuración por
rareza". Al instalarse, cada rareza usa su propia regla:

| Rareza (`card.rarity`) | Regla inicial (`point_rules.type`) | Puntos iniciales |
| --- | --- | --- |
| `comun` | `card_capture_comun` | 25 |
| `rara` | `card_capture_rara` | 50 |
| `epica` | `card_capture_epica` | 75 |
| `edicion_especial` | `card_capture_edicion_especial` | 100 |

- Los administradores pueden cambiar los puntos o desactivar una regla ("Reglas de puntos"),
  elegir otra regla para una rareza, o dejarla en "Sin puntos" ("Configuración por rareza").
  **La app nunca debe calcular ni guardar en código cuántos puntos vale una rareza**:
  siempre usa lo que devuelve el servidor.
- Los puntos pueden cambiar por **temporada o evento**: el panel puede activar un "modo de
  puntos" que cambia lo que da cada regla (por ejemplo, la mitad durante una temporada
  difícil). La API no cambia; `points.awarded` ya trae el valor del modo activo.
- Los puntos se otorgan **una sola vez por tarjeta** (solo en la captura). Perder un duelo
  no da ni quita puntos.
- Si la regla de la rareza está desactivada o la rareza está en "Sin puntos", la tarjeta
  se captura igual y `points.awarded` llega en `0`.
- Los puntos son acumulativos y el nivel nunca baja. Canjear una recompensa en el zoológico
  no descuenta puntos.
- Todo es autoritativo del servidor: no hay endpoint para sumar puntos desde la app.

## Contrato: `data.points` en la respuesta de submit

Es un campo **nuevo y aditivo**; el resto de la respuesta no cambió. Solo existe en esta
respuesta; ningún otro endpoint cambió.

```jsonc
// HTTP 201, outcome "won"
{
  "success": true,
  "message": "¡Ganaste el duelo! Quetzal Dorado ya es parte de tu colección.",
  "data": {
    "outcome": "won",
    "results": [ /* … */ ],
    "attempts": null,
    "review": null,
    "card": { /* CardResource, igual que antes */ },
    "points": {
      "awarded": 50,          // puntos otorgados por esta captura (0 si la rareza no otorga puntos)
      "total": 320,           // total acumulado del usuario DESPUÉS de esta captura
      "level": { "id": 2, "name": "Plata" },  // nivel después de la captura; null si no hay niveles
      "level_up": true        // true si esta captura lo subió de nivel
    }
  }
}
```

Con `outcome` `retry` o `locked` (HTTP 200), `data.points` es `null`.

Tipo sugerido en TypeScript:

```ts
export type DuelPoints = {
  awarded: number;
  total: number;
  level: { id: number; name: string } | null;
  level_up: boolean;
};

// Añadir a la respuesta de submit que ya existe:
// points: DuelPoints | null;
```

## Implementación en la app (checklist)

1. **Tipos:** agrega `DuelPoints` y `points: DuelPoints | null` al tipo de la respuesta de
   submit que ya usa la pantalla del duelo. Trátalo como opcional (`?? null`) por si la app
   habla con un servidor viejo.
2. **Pantalla/modal de victoria:** cuando `outcome === "won"` y `points` no es `null`:
   - si `points.awarded > 0`, muestra "+{awarded} puntos" (una animación corta de contador
     funciona bien) y el total: "Total: {total} puntos";
   - si `points.awarded === 0`, no muestres el "+0"; muestra solo la tarjeta (y el total, si
     quieres);
   - si `points.level_up`, muestra un aviso de nivel nuevo con `points.level.name`, después
     de la animación de puntos y sin tapar la tarjeta obtenida.
3. **Estado local del perfil:** si la app guarda los puntos o el nivel del usuario en caché
   (SWR, contexto o store), actualízalos con `points.total` y `points.level` en lugar de
   sumar a mano, o revalida `GET /api/profile` (devuelve `points`, `level`, el siguiente
   nivel y el progreso). No hagas las dos cosas en la misma pantalla.
4. **Textos:** en español, como el resto de la app. Usa `level.name` tal cual viene.
5. **Sin conexión:** los puntos solo existen en la respuesta del servidor. Si el submit falla
   por red, no muestres puntos supuestos. Ojo: el servidor pudo haberlo procesado; reenviar el
   mismo submit devuelve 409 ("ya fue respondido") sin `points`. En ese caso revalida
   `GET /api/profile` (puntos y nivel) y la colección en lugar de mostrar el modal de puntos.

## Cómo probar

- Gana el duelo de una tarjeta de cada rareza y revisa que `awarded` coincida con la regla
  elegida para esa rareza en "Configuración por rareza".
- Pon una rareza en "Sin puntos" (o desactiva su regla), gana una tarjeta de esa rareza:
  se captura y no aparece "+0".
- Pon a un usuario cerca del mínimo del siguiente nivel (por ejemplo, 480 puntos con el
  nivel Plata en 500) y gana una tarjeta rara: debe salir el aviso de nivel nuevo.
- Pierde un duelo: no aparece nada de puntos.
- En el panel, "Movimientos de puntos" muestra cada captura como "Captura de tarjeta: {nombre}".

Para reiniciar una tarjeta en desarrollo se pueden usar las herramientas de ZooDeck del
panel (`APP_ZOODECK_DEV=1`). Revocar una captura **no** quita los puntos, y volver a ganarla
los otorga otra vez; esto solo pasa en desarrollo.
