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
(`credits`): hasta tres, uno por recurso (ilustración, miniatura y modelo 3D).

```
{
  "success": true,
  "data": [                          ← todas las tarjetas activas con créditos
    {
      "card":    { id, name, species_name },
      "credits": [                   ← todos los créditos de esa tarjeta (1 a 3)
        { "asset": "artwork",   … },
        { "asset": "thumbnail", … },
        { "asset": "model",     … }
      ]
    },
    { "card": { … }, "credits": [ … ] },   ← siguiente tarjeta
    …
  ],
  "meta": {
    "generated_at": "…",
    "cards":   2,                    ← = data.length
    "credits": 5                     ← total de créditos de todas las tarjetas
  }
}
```

- **Orden de las tarjetas:** el mismo del hub (`sort_order`, luego `id`).
- **Orden dentro de cada tarjeta:** ilustración → miniatura → modelo 3D.
- **Agrupado por tarjeta** porque la pantalla de créditos es un `SectionList`
  (una sección por tarjeta). Si necesitas una lista plana con todos los
  créditos:

```ts
const all = data.flatMap((section) =>
  section.credits.map((credit) => ({ ...credit, card: section.card })),
);
// all.length === meta.credits
```

## Qué se incluye

| Caso | ¿Aparece? |
| --- | --- |
| Crédito de una tarjeta **activa**, capturada o no por el jugador | Sí |
| Contenido propio, por encargo o Meshy de pago | Sí (su línea lo indica: "– obra propia", "Modelo 3D creado con Meshy") |
| Tarjeta **inactiva** | **No**: su nombre puede no ser público todavía |
| Recurso subido sin licencia registrada | No (el panel lo marca como "Falta licencia") |
| Recurso eliminado de la tarjeta (sin archivo) | No |
| Tarjeta activa sin ningún crédito | No (no tiene sección) |

La app no tiene que filtrar nada: todo lo que llega se muestra.

### Modelos generados a partir de la ilustración

Un modelo de Meshy suele generarse a partir de la ilustración de la tarjeta.
Son dos obras con dos créditos, cada uno en su fila:

- **Ilustración:** quién hizo la imagen y su licencia
  (“Jaguar resting” de Jane Doe, vía Wikimedia Commons, CC BY-SA 4.0…).
- **Modelo 3D:** cómo se creó el modelo, en una sola línea que menciona la
  ilustración sin repetir su crédito: "Modelo 3D creado con Meshy a partir de
  la ilustración de la tarjeta – CC BY 4.0". `based_on_artwork` es `true`.

## Ejemplo completo

Respuesta real del endpoint con dos tarjetas activas y una inactiva
("Tarjeta secreta", que no aparece). El Jaguar tiene miniatura pero sin
licencia registrada, así que solo trae dos créditos; el Hipopótamo trae los
tres. En total: 2 tarjetas, 5 créditos.

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
          "title": "Jaguar resting",
          "author": {
            "name": "Jane Doe",
            "url": "https://commons.wikimedia.org/wiki/User:Jane_Doe"
          },
          "source": {
            "name": "wikimedia_commons",
            "label": "Wikimedia Commons",
            "url": "https://commons.wikimedia.org/wiki/File:Jaguar_resting.jpg"
          },
          "license": {
            "short_name": "CC BY-SA 4.0",
            "name": "Creative Commons Atribución-CompartirIgual 4.0",
            "url": "https://creativecommons.org/licenses/by-sa/4.0/"
          },
          "modified": true,
          "modification_note": "Recortada/adaptada para la tarjeta",
          "attribution": "“Jaguar resting” de Jane Doe, vía Wikimedia Commons, CC BY-SA 4.0, con cambios: Recortada/adaptada para la tarjeta",
          "based_on_artwork": false
        },
        {
          "id": 3,
          "asset": "model",
          "asset_label": "Modelo 3D",
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
          "based_on_artwork": true
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
          "id": 4,
          "asset": "artwork",
          "asset_label": "Ilustración",
          "title": "Hipopótamo al atardecer",
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
          "attribution": "“Hipopótamo al atardecer” – obra propia",
          "based_on_artwork": false
        },
        {
          "id": 5,
          "asset": "thumbnail",
          "asset_label": "Miniatura",
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
          "based_on_artwork": false
        },
        {
          "id": 6,
          "asset": "model",
          "asset_label": "Modelo 3D",
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
          "based_on_artwork": false
        }
      ]
    }
  ],
  "meta": {
    "generated_at": "2026-10-31T18:00:00-06:00",
    "cards": 2,
    "credits": 5
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
| `data[].credits` | array | no | Todos los créditos de la tarjeta (1 a 3). |
| `credits[].id` | integer | no | ID del crédito (único en toda la respuesta). |
| `credits[].asset` | string | no | `artwork` (ilustración de la tarjeta), `thumbnail` (miniatura) o `model` (modelo 3D). |
| `credits[].asset_label` | string | no | `Ilustración`, `Miniatura` o `Modelo 3D`. |
| `credits[].title` | string | sí | Título de la obra. |
| `credits[].author` | object | sí | `{ name: string\|null, url: string\|null }`. |
| `credits[].source` | object | sí | `{ name, label, url }` (ver abajo). |
| `credits[].source.name` | string | sí | Imágenes: `own`, `pexels`, `unsplash`, `pixabay`, `wikimedia_commons`, `other`. Modelos: `own`, `meshy`, `sketchfab`, `poly_pizza`, `poly_haven`, `other`. |
| `credits[].source.label` | string | sí | `Propio`, `Pexels`, `Unsplash`, `Pixabay`, `Wikimedia Commons`, `Meshy`, `Sketchfab`, `Poly Pizza`, `Poly Haven` u `Otro`. |
| `credits[].source.url` | string | sí | Página de la obra en el sitio de origen. |
| `credits[].license.short_name` | string | no | Nombre corto (`CC BY 4.0`, `CC0 1.0`, `Licencia de Pexels`, `Propia`…). |
| `credits[].license.name` | string | no | Nombre completo. |
| `credits[].license.url` | string | sí | Texto oficial de la licencia (`null` en obra propia, por encargo y Meshy de pago). |
| `credits[].modified` | boolean | no | La obra se modificó (recorte, escalado…). |
| `credits[].modification_note` | string | sí | Qué se cambió (solo si `modified`). |
| `credits[].attribution` | string | no | **Línea lista para mostrar**, tal cual. |
| `credits[].based_on_artwork` | boolean | no | El modelo 3D se generó a partir de la ilustración de la tarjeta (siempre `false` en imágenes). La línea `attribution` ya lo dice. |
| `meta.generated_at` | string (ISO 8601) | no | Cuándo se armó la respuesta. |
| `meta.cards` | integer | no | Número de tarjetas en `data` (`data.length`). |
| `meta.credits` | integer | no | Número total de créditos de todas las tarjetas. |

**Nunca se envían:** notas internas, la fecha de obtención, rutas o URLs de
archivos, el tipo del recurso dueño, otras fechas ni nada de tarjetas
inactivas.

### La línea `attribution`

Sigue el formato TASL (título, autor, origen, licencia):

```
“Título” de Autor, vía Origen, LICENCIA, con cambios: nota
```

Las partes sin datos se omiten. Si la licencia tiene un texto de atribución
propio (Meshy), se usa ese texto. Si en el panel se escribió un texto
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
- No hace falta tratar `based_on_artwork` aparte: la línea ya lo dice. Sirve
  si quieres un icono o agrupar el modelo con la ilustración.
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

- Cada imagen (ilustración y miniatura) y el modelo 3D de una tarjeta tienen
  su sección **Licencia y crédito** en el formulario. Subir un archivo nuevo
  exige su crédito. Volver a escalar el modelo conserva el crédito.
- Una tarjeta no se puede activar mientras algún recurso subido no tenga
  licencia. Las tarjetas ya activas siguen activas y se pueden editar.
- **Créditos y licencias** (`/admin/credits`) lista todos los recursos con su
  licencia y filtra los que faltan.
- **Obtener datos** (Wikimedia Commons) consulta la API de Commons y solo llena
  el formulario. El administrador revisa antes de guardar.
- El catálogo de licencias lo administra `LicenseSeeder` (no hay pantalla para
  editarlo). Las licencias NC/ND y GFDL no se pueden elegir salvo con
  `LICENSING_ALLOW_RESTRICTED=true`.

### Orígenes y licencias permitidas

Cada origen solo ofrece las licencias que usa esa plataforma
(`AssetSource::allowsLicense`), y solo para los recursos que ofrece.

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
- **No incluidos:** TurboSquid (su licencia exige que el modelo no se pueda
  extraer del juego, y el GLB se descarga tal cual) y los generadores
  Tripo y Hyper3D Rodin (sus planes gratuitos no dejan claro el uso
  comercial).

Código principal:

| Parte | Archivo |
| --- | --- |
| Endpoint | `app/Http/Controllers/Api/CreditController.php` |
| Consulta y caché | `app/Services/Licensing/CreditsCatalog.php`, `app/Observers/CreditsCacheObserver.php` |
| Recursos JSON | `app/Http/Resources/Credits/` |
| Línea de atribución | `app/Services/Licensing/AttributionBuilder.php`, `resources/js/lib/credits/attribution.ts` |
| Validación | `app/Concerns/AssetCreditValidationRules.php`, `app/Rules/` |
| Catálogo | `database/seeders/LicenseSeeder.php` |
| Configuración | `config/licensing.php` |
| Pruebas | `tests/Feature/Api/CreditTest.php`, `tests/Feature/Admin/CardCreditsTest.php` |
