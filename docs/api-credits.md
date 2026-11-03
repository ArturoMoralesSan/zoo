# API de créditos (`GET /api/v1/credits`)

La pantalla de créditos de la app muestra quién creó cada imagen y modelo 3D
de las tarjetas y con qué licencia. **Una sola llamada a este endpoint
devuelve todos los créditos de todas las tarjetas activas**, listos para
mostrar.

## En resumen

| | |
| --- | --- |
| Método y URL | `GET /api/v1/credits` |
| Autenticación | Ninguna (público). Funciona antes de iniciar sesión. |
| Parámetros | Ninguno. No hay filtros ni paginación. |
| Respuesta | Un JSON con **todos** los créditos de **todas** las tarjetas activas, agrupados por tarjeta. |
| Igual para todos | Sí. No depende del usuario ni de las tarjetas que haya capturado. |
| Límite | 60 peticiones por minuto por IP (`config/licensing.php` → `api.rate_limit`). |
| Caché | `ETag` / `Last-Modified`, responde `304` si nada cambió (ver [Caché](#caché)). |

```bash
curl https://zoo.example.com/api/v1/credits -H 'Accept: application/json'
```

No hay un endpoint por tarjeta: la app hace **una** llamada y recibe todo.

## Estructura de la respuesta

`data` es un **array con una entrada por tarjeta activa** que tenga al menos
un crédito. Cada entrada trae la tarjeta (`card`) y **todos** sus créditos
(`credits`).

```
{
  "success": true,
  "data": [                          ← todas las tarjetas activas con créditos
    {
      "card":    { id, name, species_name },
      "credits": [                   ← todos los créditos de esa tarjeta
        { "asset": "artwork",   "role": "origin",    … },
        { "asset": "thumbnail", "role": "origin",    … },
        { "asset": "thumbnail", "role": "component", … },
        { "asset": "model",     "role": "origin",    … },
        { "asset": "model",     "role": "based_on",  … }
      ]
    },
    { "card": { … }, "credits": [ … ] },   ← siguiente tarjeta
    …
  ],
  "meta": {
    "generated_at": "…",
    "cards":   2,                    ← = data.length
    "credits": 7                     ← total de créditos de todas las tarjetas
  }
}
```

- **Orden de las tarjetas:** el mismo del hub (`sort_order`, luego `id`).
- **Orden dentro de cada tarjeta:** por recurso (ilustración → miniatura →
  modelo 3D) y, dentro de cada recurso, por papel (origen → basado en →
  incluye).
- **Agrupado por tarjeta** porque la pantalla de créditos es un `SectionList`
  (una sección por tarjeta). Si necesitas una lista plana con todos los
  créditos:

```ts
const all = data.flatMap((section) =>
  section.credits.map((credit) => ({ ...credit, card: section.card })),
);
// all.length === meta.credits
```

## Varios créditos por recurso

Un recurso (ilustración, miniatura o modelo 3D) puede tener **varios
créditos**, cada uno con un papel (`role`):

| `role` | `role_label` | Cuántos | Qué acredita | Prefijo de `attribution` |
| --- | --- | --- | --- | --- |
| `origin` | Origen | Exactamente 1 | Quién hizo el archivo o de dónde salió | — |
| `based_on` | Basado en | 0 o más | Una obra externa usada para crearlo: la foto que se dio a Meshy, un modelo de otro sitio que se renderizó… | `Basado en: ` |
| `component` | Incluye | 0 o más | Material de terceros dentro del recurso: HDRI, texturas, objetos de la escena… | `Incluye: ` |

### Recursos hechos a partir de otros de la misma tarjeta

Cuando un recurso se hizo a partir de otro de la misma tarjeta, su crédito de
origen lo dice en la misma línea y `based_on_asset` indica cuál. El otro
recurso tiene sus propias filas, así que nada se repite:

| Caso | `based_on_asset` | Línea |
| --- | --- | --- |
| Modelo de Meshy generado desde la ilustración | `artwork` | Modelo 3D creado con Meshy a partir de la ilustración de la tarjeta – CC BY 4.0 |
| Miniatura que es un render del modelo 3D | `model` | “Jaguar en la selva”, a partir del modelo 3D de la tarjeta – obra propia |
| Miniatura recortada de la ilustración | `artwork` | … a partir de la ilustración de la tarjeta … |

Las licencias de la obra original se siguen aplicando: si la ilustración es
CC BY-SA, el modelo y sus renders también deben compartirse con CC BY-SA.

### Imágenes generadas con IA

Su crédito de origen tiene `source.name = "ai"` y una línea como
`Imagen generada con IA (Adobe Firefly)`; `license` son los términos de la
herramienta (`license.url` es `null`). Una imagen hecha solo con IA
probablemente no tiene derechos de autor, por eso no se atribuye a un autor.

## Qué se incluye

| Caso | ¿Aparece? |
| --- | --- |
| Crédito de una tarjeta **activa**, capturada o no por el jugador | Sí |
| Contenido propio, por encargo, Meshy de pago o generado con IA | Sí (su línea lo indica: "– obra propia", "Modelo 3D creado con Meshy", "Imagen generada con IA (…)") |
| Créditos "Basado en" e "Incluye" | Sí, debajo del origen de su recurso |
| Tarjeta **inactiva** | **No**: su nombre puede no ser público todavía |
| Recurso eliminado de la tarjeta (sin archivo) | No |
| Tarjeta activa sin ningún crédito | No (no tiene sección) |

La app no tiene que filtrar nada: todo lo que llega se muestra.

## Ejemplo completo

Respuesta real del endpoint con dos tarjetas activas y una inactiva
("Tarjeta secreta", que no aparece). En total: 2 tarjetas, 7 créditos.

- **Jaguar:** ilustración generada con IA; miniatura que es un render del
  modelo 3D, con un HDRI de Poly Haven en la escena ("Incluye"); modelo de
  Meshy generado a partir de la ilustración.
- **Hipopótamo:** ilustración de Wikimedia Commons, miniatura de Pexels y
  modelo de Sketchfab.

```json
{
  "success": true,
  "data": [
    {
      "card": {
        "id": 1,
        "name": "Jaguar",
        "species_name": "Jaguar"
      },
      "credits": [
        {
          "id": 2,
          "asset": "artwork",
          "asset_label": "Ilustración",
          "role": "origin",
          "role_label": "Origen",
          "title": null,
          "author": null,
          "source": {
            "name": "ai",
            "label": "Generada con IA",
            "url": null
          },
          "license": {
            "short_name": "IA · Adobe Firefly",
            "name": "Términos de Adobe Firefly (imagen generada con IA)",
            "url": null
          },
          "modified": true,
          "modification_note": "Retocada a mano",
          "attribution": "Imagen generada con IA (Adobe Firefly)",
          "based_on_asset": null
        },
        {
          "id": 3,
          "asset": "thumbnail",
          "asset_label": "Miniatura",
          "role": "origin",
          "role_label": "Origen",
          "title": "Jaguar en la selva",
          "author": null,
          "source": {
            "name": "own",
            "label": "Propio",
            "url": null
          },
          "license": {
            "short_name": "Propia",
            "name": "Obra propia",
            "url": null
          },
          "modified": false,
          "modification_note": null,
          "attribution": "“Jaguar en la selva”, a partir del modelo 3D de la tarjeta – obra propia",
          "based_on_asset": "model"
        },
        {
          "id": 4,
          "asset": "thumbnail",
          "asset_label": "Miniatura",
          "role": "component",
          "role_label": "Incluye",
          "title": "Forest Slope",
          "author": {
            "name": "Greg Zaal",
            "url": null
          },
          "source": {
            "name": "poly_haven",
            "label": "Poly Haven",
            "url": "https://polyhaven.com/a/forest_slope"
          },
          "license": {
            "short_name": "CC0 1.0",
            "name": "Creative Commons Cero 1.0 (dominio público)",
            "url": "https://creativecommons.org/publicdomain/zero/1.0/"
          },
          "modified": false,
          "modification_note": null,
          "attribution": "Incluye: “Forest Slope” de Greg Zaal, vía Poly Haven, CC0 1.0",
          "based_on_asset": null
        },
        {
          "id": 5,
          "asset": "model",
          "asset_label": "Modelo 3D",
          "role": "origin",
          "role_label": "Origen",
          "title": null,
          "author": null,
          "source": {
            "name": "meshy",
            "label": "Meshy",
            "url": null
          },
          "license": {
            "short_name": "CC BY 4.0",
            "name": "Meshy (plan gratuito) – Creative Commons Atribución 4.0",
            "url": "https://creativecommons.org/licenses/by/4.0/"
          },
          "modified": true,
          "modification_note": "Escalado para RA",
          "attribution": "Modelo 3D creado con Meshy a partir de la ilustración de la tarjeta – CC BY 4.0",
          "based_on_asset": "artwork"
        }
      ]
    },
    {
      "card": {
        "id": 2,
        "name": "Hipopótamo",
        "species_name": "Hipopótamo"
      },
      "credits": [
        {
          "id": 6,
          "asset": "artwork",
          "asset_label": "Ilustración",
          "role": "origin",
          "role_label": "Origen",
          "title": "Hippo at sunset",
          "author": {
            "name": "Jane Doe",
            "url": "https://commons.wikimedia.org/wiki/User:Jane_Doe"
          },
          "source": {
            "name": "wikimedia_commons",
            "label": "Wikimedia Commons",
            "url": "https://commons.wikimedia.org/wiki/File:Hippo_at_sunset.jpg"
          },
          "license": {
            "short_name": "CC BY-SA 4.0",
            "name": "Creative Commons Atribución-CompartirIgual 4.0",
            "url": "https://creativecommons.org/licenses/by-sa/4.0/"
          },
          "modified": true,
          "modification_note": "Recortada/adaptada para la tarjeta",
          "attribution": "“Hippo at sunset” de Jane Doe, vía Wikimedia Commons, CC BY-SA 4.0, con cambios: Recortada/adaptada para la tarjeta",
          "based_on_asset": null
        },
        {
          "id": 7,
          "asset": "thumbnail",
          "asset_label": "Miniatura",
          "role": "origin",
          "role_label": "Origen",
          "title": null,
          "author": {
            "name": "Ana López",
            "url": "https://www.pexels.com/@ana-lopez"
          },
          "source": {
            "name": "pexels",
            "label": "Pexels",
            "url": "https://www.pexels.com/photo/hippo-123/"
          },
          "license": {
            "short_name": "Licencia de Pexels",
            "name": "Licencia de Pexels",
            "url": "https://www.pexels.com/license/"
          },
          "modified": true,
          "modification_note": "Recortada/adaptada para la tarjeta",
          "attribution": "Ana López, vía Pexels, Licencia de Pexels, con cambios: Recortada/adaptada para la tarjeta",
          "based_on_asset": null
        },
        {
          "id": 8,
          "asset": "model",
          "asset_label": "Modelo 3D",
          "role": "origin",
          "role_label": "Origen",
          "title": "Hippo low poly",
          "author": {
            "name": "ana3d",
            "url": "https://sketchfab.com/ana3d"
          },
          "source": {
            "name": "sketchfab",
            "label": "Sketchfab",
            "url": "https://sketchfab.com/3d-models/hippo-123"
          },
          "license": {
            "short_name": "CC BY 4.0",
            "name": "Creative Commons Atribución 4.0",
            "url": "https://creativecommons.org/licenses/by/4.0/"
          },
          "modified": true,
          "modification_note": "Escalado para RA",
          "attribution": "“Hippo low poly” de ana3d, vía Sketchfab, CC BY 4.0, con cambios: Escalado para RA",
          "based_on_asset": null
        }
      ]
    }
  ],
  "meta": {
    "generated_at": "2026-10-31T18:00:00-06:00",
    "cards": 2,
    "credits": 7
  }
}
```

## Campos

Los datos que faltan valen `null`. Si un objeto no tiene ningún dato (un autor
sin nombre ni URL), el objeto completo vale `null`.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| `success` | boolean | no | Siempre `true` en una respuesta 200. |
| `data` | array | no | Una entrada por tarjeta activa con créditos. Vacío (`[]`) si no hay ninguno. |
| `data[].card.id` | integer | no | ID de la tarjeta (el mismo de `GET /api/cards`). |
| `data[].card.name` | string | no | Nombre de la tarjeta. Encabezado de la sección. |
| `data[].card.species_name` | string | sí | Nombre común de la especie. |
| `data[].credits` | array | no | Todos los créditos de la tarjeta, en orden (recurso, luego papel). |
| `credits[].id` | integer | no | ID del crédito (único en toda la respuesta). |
| `credits[].asset` | string | no | `artwork` (ilustración de la tarjeta), `thumbnail` (miniatura) o `model` (modelo 3D). |
| `credits[].asset_label` | string | no | `Ilustración`, `Miniatura` o `Modelo 3D`. |
| `credits[].role` | string | no | `origin` (de dónde salió el recurso; uno por recurso), `based_on` (obra en que se basa) o `component` (material que incluye). |
| `credits[].role_label` | string | no | `Origen`, `Basado en` o `Incluye`. |
| `credits[].title` | string | sí | Título de la obra. |
| `credits[].author` | object | sí | `{ name: string\|null, url: string\|null }`. |
| `credits[].source` | object | sí | `{ name, label, url }` (ver abajo). |
| `credits[].source.name` | string | sí | Imágenes: `own`, `pexels`, `unsplash`, `pixabay`, `wikimedia_commons`, `other`. Modelos: `own`, `meshy`, `sketchfab`, `poly_pizza`, `poly_haven`, `other`. Solo imágenes: `ai` (generada con IA). En "Basado en" e "Incluye" puede aparecer cualquiera. |
| `credits[].source.label` | string | sí | `Propio`, `Pexels`, `Unsplash`, `Pixabay`, `Wikimedia Commons`, `Meshy`, `Sketchfab`, `Poly Pizza`, `Poly Haven`, `Generada con IA` u `Otro`. |
| `credits[].source.url` | string | sí | Página de la obra en el sitio de origen. |
| `credits[].license.short_name` | string | no | Nombre corto (`CC BY 4.0`, `CC0 1.0`, `Licencia de Pexels`, `Propia`…). |
| `credits[].license.name` | string | no | Nombre completo. |
| `credits[].license.url` | string | sí | Texto oficial de la licencia (`null` en obra propia, por encargo, Meshy de pago e imágenes de IA). |
| `credits[].modified` | boolean | no | La obra se modificó (recorte, escalado…). |
| `credits[].modification_note` | string | sí | Qué se cambió (solo si `modified`). |
| `credits[].attribution` | string | no | **Línea lista para mostrar**, tal cual. |
| `credits[].based_on_asset` | string | sí | Otro recurso de la misma tarjeta del que se hizo este: `artwork`, `thumbnail` o `model` (solo en el origen). Ese recurso tiene sus propias filas; la línea `attribution` ya lo dice. |
| `meta.generated_at` | string (ISO 8601) | no | Cuándo se armó la respuesta. |
| `meta.cards` | integer | no | Número de tarjetas en `data` (`data.length`). |
| `meta.credits` | integer | no | Número total de créditos de todas las tarjetas. |

**Nunca se envían:** notas internas, la fecha de obtención, la indicación
(prompt) y la revisión de las imágenes de IA, rutas o URLs de archivos, el
tipo del recurso dueño, otras fechas ni nada de tarjetas inactivas.

### La línea `attribution`

Sigue el formato TASL (título, autor, origen, licencia):

```
[Basado en: | Incluye: ]“Título” de Autor, vía Origen, LICENCIA, con cambios: nota
```

Las partes sin datos se omiten. Si la licencia tiene un texto de atribución
propio (Meshy, herramientas de IA), se usa ese texto. Si en el panel se escribió un texto
personalizado, se usa ese. El contenido propio termina en "– obra propia" o
"– obra por encargo".

## Caché

El servidor arma la respuesta una vez y la guarda en caché. Se vuelve a armar
cuando cambia un crédito, una licencia, una tarjeta (activación, nombre, orden,
especie, archivos o eliminación) o el nombre de una especie.

Cabeceras de la respuesta:

```
Cache-Control: max-age=300, public
ETag: "4f1c…"
Last-Modified: Sat, 31 Oct 2026 18:00:00 GMT
```

La app puede reutilizar la respuesta 5 minutos. Después, envía el `ETag` en
`If-None-Match` (o la fecha en `If-Modified-Since`): si nada cambió, el
servidor responde **304 sin cuerpo**.

```bash
# Primera petición: 200 con el JSON y el ETag
curl -i https://zoo.example.com/api/v1/credits -H 'Accept: application/json'

# Siguiente: 304 Not Modified si no hubo cambios
curl -i https://zoo.example.com/api/v1/credits \
  -H 'Accept: application/json' \
  -H 'If-None-Match: "4f1c…"'

# También con la fecha
curl -i https://zoo.example.com/api/v1/credits \
  -H 'Accept: application/json' \
  -H 'If-Modified-Since: Sat, 31 Oct 2026 18:00:00 GMT'
```

## Errores

| Código | Cuándo | Qué hacer |
| --- | --- | --- |
| `429 Too Many Requests` | Más de 60 peticiones por minuto desde la misma IP. | Esperar los segundos de la cabecera `Retry-After` y usar la caché local. |
| `5xx` | Error del servidor. | Mostrar la última respuesta guardada, o un mensaje de reintento. |

## Cómo mostrarlo en la app

- Usa un `SectionList`: cada elemento de `data` es una sección, con
  `card.name` como encabezado y `credits` como filas.
- Muestra `attribution` **tal cual**, sin reconstruirla: el servidor ya decide
  el formato y omite lo que falta.
- Haz tocables los enlaces que existan: `license.url` (licencia), `author.url`
  (autor) y `source.url` (obra original). Abre las URLs en el navegador.
- No hace falta tratar `role` ni `based_on_asset` aparte: la línea ya lo
  dice ("Basado en: …", "a partir del modelo 3D…"). Sirven si quieres
  sangrar los créditos "Basado en"/"Incluye" bajo el origen o agruparlos por
  recurso con `asset_label`.
- Si `data` está vacío, muestra un estado vacío ("Aún no hay créditos que
  mostrar").
- Guarda la última respuesta y su `ETag` para mostrarla sin conexión y para
  las peticiones condicionales.

```tsx
<SectionList
  sections={data.map((section) => ({ title: section.card.name, data: section.credits }))}
  keyExtractor={(credit) => String(credit.id)}
  renderSectionHeader={({ section }) => <Text style={styles.header}>{section.title}</Text>}
  renderItem={({ item }) => <CreditRow credit={item} />}
  ListEmptyComponent={<Text>Aún no hay créditos que mostrar.</Text>}
/>
```

## Panel

Los créditos se gestionan en la sección **Créditos** de cada tarjeta
(`/admin/cards/{card}/credits`), igual que sus preguntas y códigos QR. Se
llega desde "Gestionar créditos" en el detalle o el formulario de la tarjeta.

**Flujo:**

1. Crea o edita la tarjeta y sube sus archivos. Una tarjeta nueva con
   archivos se crea **inactiva** y se abre su sección Créditos.
2. En Créditos, cada recurso (ilustración, miniatura, modelo 3D) muestra su
   estado ("Licencia completa", "Falta licencia" o "Sin archivo"), sus
   créditos y avisos de la cadena de licencias.
3. **Agregar origen** (uno por recurso), **Basado en** o **Incluye**. El
   formulario va por pasos:
   1. Recurso.
   2. Tipo de crédito.
   3. Solo el origen: si se hizo a partir de otro recurso de la tarjeta
      (render del modelo, recorte de la ilustración, modelo generado desde la
      ilustración) o si es un render de un modelo de otro sitio. En ese caso,
      al guardar se pasa directo a registrar el modelo como "Basado en".
   4. Origen, licencia y datos, con la vista previa de la línea.
4. **Imágenes generadas con IA:** origen "Generada con IA", la herramienta
   (licencia), la indicación (opcional, solo el panel) y una revisión
   obligatoria: sin personas reales, personajes, marcas ni logotipos; sin
   imitar a un artista vivo; animal revisado. Los retoques hechos a mano se
   describen en "La imagen fue modificada".
5. Al final, "Vista previa en la app" muestra las filas exactas que enviará
   este endpoint para la tarjeta.
6. Activa la tarjeta. No se puede activar mientras algún recurso no tenga
   **licencia completa**: su origen y, si se hizo a partir de otro recurso,
   también el origen de ese recurso.

Otros puntos:

- Volver a escalar el modelo conserva sus créditos. Reemplazar un archivo
  también los conserva, pero el aviso al guardar pide revisarlos.
- Eliminar una imagen, el modelo o la tarjeta elimina sus créditos.
- **Créditos y licencias** (`/admin/credits`) lista todos los créditos de
  todas las tarjetas, con filtros por origen, licencia, recurso, papel y
  "Falta licencia".
- **Obtener datos** (Wikimedia Commons) consulta la API de Commons y solo llena
  el formulario. El administrador revisa antes de guardar.
- El catálogo de licencias lo administra `LicenseSeeder` (no hay pantalla para
  editarlo). Las licencias NC/ND y GFDL no se pueden elegir salvo con
  `LICENSING_ALLOW_RESTRICTED=true`.

### Orígenes y licencias permitidas

Cada origen solo ofrece las licencias que usa esa plataforma
(`AssetSource::allowsLicense`). Como **origen**, cada uno solo aplica a los
recursos de la tabla; como **Basado en** o **Incluye**, cualquiera aplica a
cualquier recurso (una imagen puede ser el render de un modelo de Sketchfab).

| Origen | Recursos | Licencias |
| --- | --- | --- |
| Propio | Imágenes y modelo | Obra propia, obra por encargo |
| Pexels | Imágenes | Licencia de Pexels |
| Unsplash | Imágenes | Licencia de Unsplash |
| Pixabay | Imágenes | Licencia de contenido de Pixabay |
| Wikimedia Commons | Imágenes | CC0, dominio público, CC BY, CC BY-SA, GFDL* (Commons no acepta NC ni ND) |
| Meshy | Modelo | Meshy gratuito (CC BY 4.0), Meshy de pago (privada) |
| Sketchfab | Modelo | CC0, CC BY, CC BY-SA, NC/ND\* (la que eligió el autor) |
| Poly Pizza | Modelo | CC0 o CC BY (según el modelo) |
| Poly Haven | Modelo | CC0 |
| Generada con IA | Imágenes | Términos de Adobe Firefly, ChatGPT / DALL·E, Google Gemini, Midjourney o Stable Diffusion |
| Otro | Imágenes y modelo | CC0, dominio público, CC BY, CC BY-SA, GFDL*, NC/ND* |

\* No seleccionables salvo con `LICENSING_ALLOW_RESTRICTED=true`.

Notas de las plataformas (según sus términos):

- **Pexels:** atribución opcional; no vender la imagen sin modificarla, no
  redistribuirla en otros bancos, no mostrar personas de forma ofensiva ni
  sugerir que una persona o marca respalda el juego.
- **Unsplash:** atribución opcional; no venderla sin una modificación
  significativa ni reunir imágenes para un servicio similar.
- **Pixabay:** atribución opcional; no venderla ni distribuirla tal cual, y
  el contenido con marcas o logotipos reconocibles no se puede usar con fines
  comerciales.
- **Meshy:** el plan gratuito da CC BY 4.0 (uso comercial citando a Meshy);
  los planes de pago dan una licencia privada sin atribución, siempre que el
  modelo no se publique en la comunidad de Meshy. Cuenta el plan con el que
  se generó el modelo.
- **Sketchfab:** cada autor elige la licencia; revisa la de la página del
  modelo (los títulos pueden decir otra cosa). No se admite la licencia
  Standard de Sketchfab: prohíbe redistribuir el modelo por separado y el
  juego descarga el archivo GLB.
- **Poly Pizza:** mezcla de CC0 y CC BY (los modelos del antiguo Google Poly
  son CC BY 3.0). **Poly Haven:** todo es CC0.
- **Imágenes generadas con IA** (una imagen hecha solo con IA probablemente
  no tiene derechos de autor: nadie la posee en exclusiva):
  - **Adobe Firefly:** uso comercial con los modelos propios de Firefly (no
    con modelos de terceros dentro de la app ni funciones beta); el plan
    gratuito agrega marca de agua.
  - **ChatGPT / DALL·E:** OpenAI cede la imagen al usuario y permite uso
    comercial; no se debe presentar como hecha por una persona.
  - **Google Gemini:** usar un plan de pago; el gratuito no deja claro el uso
    comercial. Las imágenes llevan la marca invisible SynthID.
  - **Midjourney:** requiere plan de pago; una empresa con más de US$1 M de
    ingresos al año necesita Pro o Mega.
  - **Stable Diffusion:** SD 1.5 y SDXL (OpenRAIL) permiten uso comercial;
    SD 3 y 3.5 solo con ingresos menores a US$1 M al año (Stability
    Community License).
- **No incluidos:** TurboSquid (su licencia exige que el modelo no se pueda
  extraer del juego, y el GLB se descarga tal cual) y los generadores
  Tripo y Hyper3D Rodin (sus planes gratuitos no dejan claro el uso
  comercial).

Código principal:

| Parte | Archivo |
| --- | --- |
| Endpoint | `app/Http/Controllers/Api/CreditController.php` |
| Sección Créditos de la tarjeta | `app/Http/Controllers/Admin/CardCreditController.php`, `resources/js/pages/admin/card-credits/`, `resources/js/components/admin/cards/CardCreditForm.vue` |
| Modelo | `app/Models/Credit.php`, `app/Enums/CreditRole.php` |
| Consulta y caché | `app/Services/Licensing/CreditsCatalog.php`, `app/Observers/CreditsCacheObserver.php` |
| Recursos JSON | `app/Http/Resources/Credits/` |
| Línea de atribución | `app/Services/Licensing/AttributionBuilder.php`, `resources/js/lib/credits/attribution.ts` |
| Validación | `app/Concerns/AssetCreditValidationRules.php`, `app/Rules/` |
| Catálogo | `database/seeders/LicenseSeeder.php` |
| Configuración | `config/licensing.php` |
| Pruebas | `tests/Feature/Api/CreditTest.php`, `tests/Feature/Admin/CardCreditSectionTest.php` |
