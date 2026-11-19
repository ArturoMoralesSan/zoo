# Puntos por capturar tarjetas: guía para la app móvil

> **Estado:** los puntos por captura (`data.points`) ya están en el servidor y en la app.
> Las **temporadas de puntos** (sección "Temporadas de puntos", 2026-11-19) están en el
> servidor; la parte de la app todavía no.
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
  difícil). `points.awarded` ya trae el valor del modo activo. Si el modo es visible, la
  app puede explicarlo: ver "Temporadas de puntos".
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
      "level_up": true,       // true si esta captura lo subió de nivel
      "season": null          // nuevo y opcional; ver "Temporadas de puntos"
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

## Temporadas de puntos

El panel puede activar un **modo de puntos** para una temporada o evento: cambia cuántos
puntos da cada regla (por ejemplo, una temporada más difícil con menos puntos por captura,
o un fin de semana con el doble). Solo hay uno activo a la vez, y se cambia a mano.

Cada modo decide si se **muestra a los visitantes**, con un título y un mensaje propios
(el nombre interno del modo nunca llega a la app). Si está oculto, los puntos cambian
igual, pero la API responde como si no hubiera temporada.

### Compatibilidad: la app actual no se rompe

Todo es **aditivo**. Revisado en `zooApp`:

- `api<T>()` (`src/services/api.ts`) hace `response.json()` y solo convierte el tipo; no
  valida el esquema en tiempo de ejecución, así que una clave nueva no falla.
- `DuelPoints.tsx` desestructura `{ awarded, total, level, level_up }` e ignora el resto.

Las versiones publicadas ignoran `points.season` y no llaman al endpoint nuevo. Las nuevas
deben tratar ambos como opcionales (`?? null`) por si hablan con un servidor anterior.

### `GET /api/points/season`

Requiere sesión (`auth:sanctum`), `throttle:60,1`.

```jsonc
// Sin temporada visible (no hay modo activo, o está oculto)
{ "success": true, "data": null }

// Con temporada visible
{
  "success": true,
  "data": {
    "title": "¡Temporada de Halloween!",
    "message": "Las cartas épicas dan el doble de puntos.",  // puede ser null
    "cards": [                         // capturas que cambian: una entrada por rareza, en orden de rareza
      {
        "rarity": "epica",             // igual que card.rarity
        "rarity_label": "Épica",
        "base_points": 75,             // puntos normales por capturar esta rareza
        "points": 150                  // puntos en la temporada; 0 = no da puntos (la tarjeta se captura igual)
      }
    ],
    "other": [                         // otras acciones que cambian (boletos, donaciones, eventos…), por nombre
      {
        "type": "ticket_purchase",     // clave estable; no la uses para textos ni íconos
        "name": "Compra de boleto",    // muéstralo tal cual
        "base_points": 10,
        "points": 20
      }
    ]
  }
}
```

- `cards` y `other` siempre vienen (pueden estar vacías, incluso con temporada: solo aviso).
- Lo que no aparece en ninguna lista da sus puntos normales.
- Las rarezas que comparten regla aparecen cada una con su entrada. Una regla que usa alguna
  rareza aparece solo en `cards`, nunca repetida en `other`.
- **No calcules puntos con esto.** Es para explicar la temporada; los puntos reales siempre
  llegan en la respuesta de la acción (`data.points.awarded`).

### `data.points.season` al ganar un duelo

```jsonc
"points": {
  "awarded": 30,
  "total": 350,
  "level": { "id": 2, "name": "Plata" },
  "level_up": false,
  "season": {                          // null si no hay temporada visible
    "title": "Temporada difícil",
    "message": "Las capturas dan menos puntos.",   // puede ser null
    "base_points": 50                  // lo que daría esta tarjeta sin temporada; null si su rareza no da puntos
  }
}
```

`base_points` permite mostrar el cambio sin otra petición: "+30 pts (normalmente 50)".
Si `season` existe pero `awarded === base_points`, la temporada no cambió esa tarjeta: no
muestres una comparación.

### Implementación en la app (checklist)

1. **Tipos** (`src/types/cards.ts`): agrega a `DuelPoints`
   `season?: { title: string; message: string | null; base_points: number | null } | null`.
   Crea `PointSeason`, `PointSeasonCardChange` y `PointSeasonRuleChange` con la forma del
   endpoint (`cards` y `other`).
2. **Servicio + hook:** `getPointSeason()` en `src/services/` con `api<T>()`, y un hook SWR
   en `src/hooks/` (ahí van los hooks de datos según el `AGENTS.md` de la app). Revalida al
   volver a primer plano; no hace falta sondear: la temporada cambia pocas veces.
3. **Aviso de temporada:** si `data` no es `null`, muestra un banner con `title` y `message`
   en ZooDeck (por ejemplo, sobre el mapa en `DuelMapHeader`). Si `data` es `null`, no
   muestres nada. Permite cerrarlo, y vuelve a mostrarlo solo si cambia el `title`.
4. **Detalle opcional:** desde el banner, una hoja con dos secciones, en este orden:
   - **Cartas** (`cards`): "Cartas épicas: ~~75~~ 150 pts". Usa `rarity_label`, o los
     nombres y colores de rareza que la app ya tenga, por `rarity`.
   - **Otras acciones** (`other`): "Compra de boleto: ~~10~~ 20 pts", con `name` tal cual.
     No dependas de `type` para textos o íconos: el zoológico puede crear acciones nuevas.
   Oculta la sección que venga vacía; con `points: 0` muestra "Sin puntos" en lugar de "0 pts".
5. **Victoria** (`src/components/duel/DuelPoints.tsx`): si `points.season` existe, agrega
   bajo el contador una línea con `season.title`, y "normalmente {base_points}" si
   `base_points !== null && base_points !== awarded`. Con `awarded === 0` y temporada,
   muestra el título para que se entienda por qué no ganó puntos.
6. **Textos:** usa `title` y `message` tal cual vienen; los escribe el zoológico.

## Cómo probar

- Gana el duelo de una tarjeta de cada rareza y revisa que `awarded` coincida con la regla
  elegida para esa rareza en "Configuración por rareza".
- Pon una rareza en "Sin puntos" (o desactiva su regla), gana una tarjeta de esa rareza:
  se captura y no aparece "+0".
- Pon a un usuario cerca del mínimo del siguiente nivel (por ejemplo, 480 puntos con el
  nivel Plata en 500) y gana una tarjeta rara: debe salir el aviso de nivel nuevo.
- Pierde un duelo: no aparece nada de puntos.
- En el panel, "Movimientos de puntos" muestra cada captura como "Captura de tarjeta: {nombre}".
- Temporadas: en "Modos de puntos" crea un modo con "Mostrar a los visitantes" activado y
  la captura rara a 30, y actívalo. `GET /api/points/season` lo lista; ganar una tarjeta rara
  da 30 con `season.base_points: 50`. Apaga "Mostrar a los visitantes": el endpoint devuelve
  `null` y `season` llega en `null`, pero la captura sigue dando 30. Vuelve a los puntos base
  al terminar.

Para reiniciar una tarjeta en desarrollo se pueden usar las herramientas de ZooDeck del
panel (`APP_ZOODECK_DEV=1`). Revocar una captura **no** quita los puntos, y volver a ganarla
los otorga otra vez; esto solo pasa en desarrollo.
