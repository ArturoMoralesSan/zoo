# Punto focal de las imágenes de especies (app)

> **Estado (2026-11-27):** el servidor está listo; falta la app (`../zooApp`).
> Para quien lo implemente allí: manda el `AGENTS.md` de ese repositorio.

La app recorta las fotos de especies con `cover` en marcos de distinta forma (ficha vertical,
tarjetas de lista anchas, mosaico de "Mi zoo", tira de la galería). El panel deja marcar un
**punto focal** por imagen para que el animal no quede fuera.

## Contrato (todo es aditivo y puede ser null)

- `GET /api/species`: cada especie agrega `image_focal: { x, y } | null`. `image` ahora es la
  imagen principal; si no hay, la miniatura y después la primera activa.
- `GET /api/species/{id}`: cada elemento de `images` agrega `focal_x` y `focal_y`
  (`number | null`). Solo las de tipo `main` y `gallery` los usan.
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

Las imágenes de tarjetas no lo necesitan: la ilustración y la miniatura ya se recortan a su
proporción en el panel.
