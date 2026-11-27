# Punto focal de las imágenes (app)

> **Estado (2026-11-27):** el servidor está listo; falta la app (`../zooApp`).
> Para quien lo implemente allí: manda el `AGENTS.md` de ese repositorio.

La app recorta con `cover` las fotos de especies y la miniatura de las tarjetas en marcos de
distinta forma. El panel deja marcar un **punto focal** por imagen para que el animal no quede
fuera del marco.

## Contrato (todo es aditivo y puede ser null)

- `GET /api/species`: cada especie agrega `image_focal: { x, y } | null`. `image` ahora es la
  imagen principal; si no hay, la miniatura y después la primera activa.
- `GET /api/species/{id}`: cada elemento de `images` agrega `focal_x` y `focal_y`
  (`number | null`). Solo las de tipo `main` y `gallery` los usan.
- Miniatura de la tarjeta: `thumbnail_focal: { x, y } | null` junto a cada `thumbnail_image` de
  una tarjeta obtenida (`GET /api/cards`, `card` al ganar un duelo y en el 409 "ya la tienes",
  `CardResource`) y en `card` de `POST /api/duels` (el reto). La miniatura se sube 1:1. El mapa
  ZooDeck (`GET /api/map/species`) no lo envía: allí es un círculo sobre un cuadrado y no recorta.
- `x` e `y` van de 0 a 1, como fracción del ancho y del alto de la imagen, desde la esquina
  superior izquierda. `null` = centro (lo de antes).

## Cómo aplicarlo

Igual que `object-position` de CSS, o `contentPosition` de expo-image con porcentajes:
`{ left: x * 100 + '%', top: y * 100 + '%' }`. El punto que está al x % de la foto queda al x %
del marco: siempre a la vista, no centrado. Las vistas previas del panel usan esta misma regla
(`lib/focalPoint.ts`), así que no hay que inventar otra fórmula.

`expo-image` ya está instalado y en el binario nativo: el cambio es solo JS.

## Dónde

| Componente | Imagen | Punto |
| --- | --- | --- |
| `SpeciesHero` | `heroImage.url` | `heroImage.focal_x/y` |
| `SpeciesCard`, `SpeciesTile` | `species.image` | `species.image_focal` |
| `SpeciesGallery` (tira) | `image.url` | `image.focal_x/y` |
| `SpeciesImageViewer` | — | sin cambios (`contain`, no recorta) |
| `CardThumbnail` (mazo 1.4:1, colección, fila de packs) | `card.thumbnail_image` | `card.thumbnail_focal` |
| `DuelDialog` con imagen tipo ilustración (reto, perdiste, bloqueada: ~2.3:1) | miniatura del duelo | `duel.card.thumbnail_focal` |
| `useCardFace` cuando no hay imagen final (carrusel, visor 3D, diálogo de victoria: 0.7:1) | miniatura | `card.thumbnail_focal` |
| `DuelMarker` | miniatura | no hace falta (círculo, no recorta) |

La ilustración y la imagen final de la tarjeta no lo necesitan: ya se recortan a la proporción de
la tarjeta en el panel.

Marcos que el panel muestra como vista previa (teléfono de 400 px): especies en `APP_FRAMES`,
miniatura de tarjeta en `CARD_THUMBNAIL_FRAMES` (Duelo 320×140, Mazo 174×124, Colección 115×109,
Sin tarjeta final 112×160).
