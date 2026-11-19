# Puntos en la app: guía general

> **Estado (2026-11-19):** el servidor está listo. En la app ya están los puntos por
> capturar tarjetas; las **temporadas de puntos** (aviso, ícono y color) todavía no.
> Instrucciones para implementar el cliente en `../zooApp` (ZooDeck, Expo + ViroReact).
>
> Para quien implemente esto en `zooApp`: sigue el `AGENTS.md` de ese repositorio
> (estilo, componentes, dónde van los hooks de datos, commits). Ahí manda ese archivo
> sobre este.
>
> Los puntos por capturar tarjetas (`data.points` al ganar un duelo) tienen su propia
> guía: [card-points-app.md](card-points-app.md).

## Cómo funcionan los puntos

- **Reglas de puntos:** cada acción que da puntos es una regla con sus **puntos base**
  (comprar un boleto, capturar una tarjeta de cierta rareza, donar, asistir a un evento…).
  El zoológico las crea y edita en el panel ("Reglas de puntos"); pueden aparecer reglas
  nuevas en cualquier momento.
- **Autoritativo del servidor:** la app nunca suma ni calcula puntos. Los puntos ganados
  llegan en la respuesta de cada acción (por ejemplo, `data.points` al ganar un duelo) y
  el total y el nivel, en `GET /api/profile`.
- **Niveles:** dependen del total acumulado y **nunca bajan**. Canjear una recompensa en el
  zoológico no descuenta puntos (los puntos de la recompensa son un mínimo, no un precio).
- **Hoy dan puntos:** la compra de boletos (en la app y en taquilla) y la captura de tarjetas.
  Las reglas de donaciones y eventos existen, pero todavía nada las otorga.

## Temporadas de puntos (modos)

El zoológico puede activar un **modo de puntos** para una temporada o evento: cambia
cuántos puntos da cada regla (por ejemplo, una temporada más difícil con la mitad por
captura, o un fin de semana con el doble por boleto). Solo hay uno activo a la vez y se
cambia a mano desde el panel. Sin modo activo, cada regla da sus puntos base.

Cada modo decide si se **muestra a los visitantes**:

| En el panel | Puntos | Lo que ve la app |
| --- | --- | --- |
| Sin modo activo | Base | `data: null` |
| Modo activo, oculto | Los del modo | `data: null` (los puntos cambian sin aviso) |
| Modo activo, visible | Los del modo | Título, mensaje, ícono, color y qué cambia |

El nombre y la descripción internos del modo **nunca** llegan a la app; solo el aviso
público (título y mensaje) que escribe el zoológico.

### Compatibilidad: la app actual no se rompe

Todo es **aditivo**. Revisado en `zooApp` (`main`):

- `api<T>()` (`src/services/api.ts`) hace `response.json()` y solo convierte el tipo; no
  valida el esquema en tiempo de ejecución, así que las claves nuevas no fallan.
- Ningún código usa todavía `points/season` ni `season`.

Las versiones publicadas no llaman al endpoint nuevo e ignoran los campos nuevos. Las
nuevas deben tratarlos como opcionales (`?? null`) por si hablan con un servidor anterior.

## `GET /api/points/season`

Requiere sesión (`auth:sanctum`), `throttle:60,1`. Documentado también en Scramble
(`/docs/api`, etiqueta "Puntos").

```jsonc
// Sin temporada visible (no hay modo activo, o está oculto)
{ "success": true, "data": null }

// Con temporada visible
{
  "success": true,
  "data": {
    "title": "¡Temporada de Halloween!",
    "message": "Las cartas épicas dan el doble de puntos.",  // puede ser null
    "icon": "pumpkin",               // clave del catálogo; null = ícono predeterminado
    "color": {                       // null = colores predeterminados
      "key": "calabaza",
      "accent": "#B24E0A",           // círculo del ícono / acento fuerte
      "on_accent": "#FFFFFF",        // ícono o texto encima de accent
      "soft": "#FDEBDC",             // fondo del aviso
      "text": "#6B2E05"              // texto encima de soft
    },
    "cards": [                       // capturas que cambian: una entrada por rareza, en orden de rareza
      {
        "rarity": "epica",           // igual que card.rarity
        "rarity_label": "Épica",
        "base_points": 75,           // puntos normales por capturar esta rareza
        "points": 150                // puntos en la temporada; 0 = no da puntos (la tarjeta se captura igual)
      }
    ],
    "other": [                       // otras acciones que cambian (boletos, donaciones, eventos…), por nombre
      {
        "type": "ticket_purchase",   // clave estable; no la uses para textos ni íconos
        "name": "Compra de boleto",  // muéstralo tal cual
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
- **No calcules puntos con esto.** Es para avisar y explicar; los puntos reales siempre
  llegan en la respuesta de la acción.

## Ícono y color

El zoológico elige el ícono y el color del aviso en el panel, o deja "Predeterminado".

### Predeterminado

Si `icon` es `null`, o es una clave que la app no conoce (el catálogo puede crecer sin
publicar otra versión de la app), usa **`SparklesIcon`**. Si `color` es `null`, usa los
colores de la marca: los mismos tonos que `esmeralda` en la tabla de abajo
(`esmeralda` `#06733A` con blanco encima, fondo `hoja` `#D3EBDB`, texto `bosque` `#053D1E`).
El panel muestra esa misma vista previa.

### Cómo usar los tonos

| Parte del aviso | Fondo | Encima |
| --- | --- | --- |
| Aviso (tarjeta o banner) | `soft` | `text` (título y mensaje) |
| Círculo del ícono, botón o chip fuerte | `accent` | `on_accent` (ícono o texto) |

Todos los pares tienen contraste de al menos 4.5:1 (lo verifica una prueba en el servidor).
No mezcles tonos de colores distintos ni pongas `text` sobre `accent`.

### Íconos (clave → componente)

La API manda solo la **clave**. La app la traduce con esta tabla a
`@hugeicons/react-native` (`<HugeiconsIcon icon={PumpkinIcon} />`), el mismo set que ya
usa. Copia el mapa en un archivo propio, por ejemplo `src/constants/seasonIcons.ts`:

```ts
import { PumpkinIcon, SparklesIcon /* … */ } from '@hugeicons/core-free-icons'

export const SEASON_ICONS: Record<string, typeof SparklesIcon> = {
  pumpkin: PumpkinIcon,
  // …
}

export const seasonIcon = (key: string | null | undefined) =>
  (key && SEASON_ICONS[key]) || SparklesIcon
```

| Clave (`icon`) | Ícono en `@hugeicons/core-free-icons` | Nombre | Grupo |
| --- | --- | --- | --- |
| `pine-tree` | `PineTreeIcon` | Pino navideño | Festividades |
| `gift` | `GiftIcon` | Regalo | Festividades |
| `snowflake` | `SnowflakeIcon` | Copo de nieve | Festividades |
| `bell` | `BellIcon` | Campana | Festividades |
| `pumpkin` | `PumpkinIcon` | Calabaza | Festividades |
| `ghost` | `GhostIcon` | Fantasma | Festividades |
| `skull` | `SkullIcon` | Calavera | Festividades |
| `flower` | `FlowerIcon` | Flor | Festividades |
| `heart` | `HeartIcon` | Corazón | Festividades |
| `rose` | `RoseIcon` | Rosa | Festividades |
| `egg` | `EggIcon` | Huevo | Festividades |
| `rabbit` | `RabbitIcon` | Conejo | Festividades |
| `flag` | `FlagIcon` | Bandera | Festividades |
| `fireworks` | `FireworksIcon` | Fuegos artificiales | Festividades |
| `sun` | `SunIcon` | Sol | Estaciones |
| `beach` | `BeachIcon` | Playa | Estaciones |
| `umbrella` | `UmbrellaIcon` | Paraguas | Estaciones |
| `ice-cream` | `IceCreamIcon` | Helado | Estaciones |
| `watermelon` | `WatermelonIcon` | Sandía | Estaciones |
| `cactus` | `CactusIcon` | Cactus | Estaciones |
| `leaf` | `LeafIcon` | Hoja | Estaciones |
| `tulip` | `TulipIcon` | Tulipán | Estaciones |
| `sprout` | `SproutIcon` | Brote | Estaciones |
| `snow` | `SnowIcon` | Nieve | Estaciones |
| `moon` | `MoonIcon` | Luna | Estaciones |
| `mountain` | `MountainIcon` | Montaña | Estaciones |
| `campfire` | `CampfireIcon` | Fogata | Estaciones |
| `paw-print` | `PawPrintIcon` | Huella | Animales |
| `panda` | `PandaIcon` | Panda | Animales |
| `turtle` | `TurtleIcon` | Tortuga | Animales |
| `bird` | `BirdIcon` | Ave | Animales |
| `cat` | `CatIcon` | Felino | Animales |
| `fish` | `FishIcon` | Pez | Animales |
| `horse` | `HorseIcon` | Caballo | Animales |
| `squirrel` | `SquirrelIcon` | Ardilla | Animales |
| `snail` | `SnailIcon` | Caracol | Animales |
| `crab` | `CrabIcon` | Cangrejo | Animales |
| `octopus` | `OctopusIcon` | Pulpo | Animales |
| `bug` | `BugIcon` | Insecto | Animales |
| `shell` | `ShellIcon` | Concha | Animales |
| `sparkles` | `SparklesIcon` | Destellos | Eventos |
| `party-popper` | `PartyPopperIcon` | Fiesta | Eventos |
| `balloons` | `BalloonsIcon` | Globos | Eventos |
| `cake` | `CakeIcon` | Pastel | Eventos |
| `graduation-cap` | `GraduationCapIcon` | Graduación | Eventos |
| `school` | `SchoolIcon` | Escuela | Eventos |
| `star` | `StarIcon` | Estrella | Eventos |
| `crown` | `CrownIcon` | Corona | Eventos |
| `ticket` | `TicketIcon` | Boleto | Eventos |

La fuente de verdad es `resources/data/point-season-styles.json` en el repositorio del
servidor. Las claves **nunca cambian**; solo se agregan nuevas.

### Colores

| Clave (`color.key`) | Nombre | `accent` | `on_accent` | `soft` | `text` |
| --- | --- | --- | --- | --- | --- |
| `esmeralda` | Esmeralda | `#06733A` | `#FFFFFF` | `#D3EBDB` | `#053D1E` |
| `bosque` | Bosque | `#053D1E` | `#FFFFFF` | `#EEF4F0` | `#053D1E` |
| `lima` | Lima | `#B8F25C` | `#0B2416` | `#EEF9D9` | `#2F4A0C` |
| `turquesa` | Turquesa | `#0D7373` | `#FFFFFF` | `#DCF1EF` | `#0A4A4A` |
| `azul-invierno` | Azul invierno | `#1F5F8B` | `#FFFFFF` | `#E2EDF5` | `#123B57` |
| `morado` | Morado | `#5B3A8C` | `#FFFFFF` | `#EEE7F6` | `#3A2160` |
| `rosa` | Rosa | `#B8235E` | `#FFFFFF` | `#FBE5EE` | `#7A1240` |
| `rojo-navideno` | Rojo navideño | `#B42318` | `#FFFFFF` | `#FDECEA` | `#7A1A12` |
| `calabaza` | Calabaza | `#B24E0A` | `#FFFFFF` | `#FDEBDC` | `#6B2E05` |
| `cempasuchil` | Cempasúchil | `#E8A317` | `#3A2400` | `#FFF1CC` | `#6B4300` |
| `dorado` | Dorado | `#8C6A12` | `#FFFFFF` | `#F8EED3` | `#5A430A` |

Si la app ya tiene un tono propio para alguna clave (por ejemplo `esmeralda`), puede
usarlo; si no, usa los valores que llegan en `color`.

## Implementación en la app (checklist)

1. **Tipos** (por ejemplo `src/types/points.ts`):
   ```ts
   export type SeasonColor = {
     key: string
     accent: string
     on_accent: string
     soft: string
     text: string
   }
   export type PointSeasonCardChange = {
     rarity: 'comun' | 'rara' | 'epica' | 'edicion_especial'
     rarity_label: string
     base_points: number
     points: number
   }
   export type PointSeasonRuleChange = {
     type: string
     name: string
     base_points: number
     points: number
   }
   export type PointSeason = {
     title: string
     message: string | null
     icon: string | null
     color: SeasonColor | null
     cards: PointSeasonCardChange[]
     other: PointSeasonRuleChange[]
   }
   ```
2. **Íconos:** el mapa `SEASON_ICONS` de arriba, con `SparklesIcon` como respaldo.
3. **Servicio + hook:** `getPointSeason()` en `src/services/` con `api<T>()`, y un hook SWR
   en `src/hooks/` (ahí van los hooks de datos según el `AGENTS.md` de la app). Revalida al
   volver a primer plano; no hace falta sondear: la temporada cambia pocas veces.
4. **Aviso de temporada:** si `data` no es `null`, muestra un banner en ZooDeck (por
   ejemplo, sobre el mapa en `DuelMapHeader`): fondo `soft`, título y mensaje en `text`, y
   el ícono en un círculo `accent` con `on_accent`. Si `data` es `null`, no muestres nada.
   Permite cerrarlo, y vuelve a mostrarlo solo si cambia el `title`.
5. **Detalle opcional:** desde el banner, una hoja con dos secciones, en este orden:
   - **Cartas** (`cards`): "Cartas épicas: ~~75~~ 150 pts". Usa `rarity_label`, o los
     nombres y colores de rareza que la app ya tenga, por `rarity`.
   - **Otras acciones** (`other`): "Compra de boleto: ~~10~~ 20 pts", con `name` tal cual.
     No dependas de `type` para textos o íconos: el zoológico puede crear acciones nuevas.
   Oculta la sección que venga vacía; con `points: 0` muestra "Sin puntos" en lugar de "0 pts".
6. **Victoria del duelo:** ver [card-points-app.md](card-points-app.md) (`points.season`
   trae el mismo `icon` y `color`).
7. **Textos:** usa `title`, `message` y `name` tal cual vienen; los escribe el zoológico.

## Cómo probar

1. En el panel, "Modos de puntos" → crea un modo, por ejemplo "Halloween": captura épica a
   150 y compra de boleto a 20. En "Aviso para los visitantes" activa "Mostrar a los
   visitantes", pon título y mensaje, elige el ícono "Calabaza" y el color "Calabaza".
2. Actívalo. `GET /api/points/season` devuelve el aviso con `icon: "pumpkin"`, el color,
   la épica en `cards` y el boleto en `other`. La app muestra el banner con esos colores.
3. Cambia ícono y color a "Predeterminado": llegan en `null` y la app usa `SparklesIcon` y
   los colores de la marca.
4. Apaga "Mostrar a los visitantes": el endpoint devuelve `data: null` y el banner
   desaparece, pero los puntos siguen siendo los del modo.
5. "Volver a puntos base" al terminar.
