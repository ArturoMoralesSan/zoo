# Tamaño real de los modelos 3D de las tarjetas

Los modelos 3D (`.glb`) de las tarjetas se guardan con su **tamaño real**: en
glTF y en ViroReact, 1 unidad = 1 metro. La app muestra el modelo con escala
`[1, 1, 1]`, así que el propio archivo debe medir exactamente lo que el
administrador indicó y tener el origen en el lugar correcto.

## Cómo funciona

```
Navegador (panel)                                   Servidor (PHP)
─────────────────                                   ──────────────
1. El admin elige el .glb       ─┐
2. inspectGlb() lo mide          │ @gltf-transform (import dinámico)
3. Escribe el alto en metros     │
4. Al guardar: resizeGlb()      ─┘
   · agrega un nodo "ScaleRoot" con escala uniforme y desplazamiento
   · quita la compresión (Draco, Meshopt, cuantización)
   · vuelve a leer el resultado y verifica el contrato
5. Envía original + escalado + medidas  ───────────▶ 6. Form Request: .glb, encabezado glTF 2, tamaño, alto
                                                      7. GlbInspector lee solo el encabezado y el JSON
                                                         y vuelve a verificar el contrato
                                                      8. Guarda (en una transacción):
                                                         · escalado → disco public  cards/{id}/models/…
                                                         · original → disco local   cards/{id}/models/originals/…
                                                      9. Mensaje de éxito + advertencias (toast)
```

- `cards.model_file` sigue siendo **el archivo que descarga el juego** (el escalado).
- `cards.model_original_file` es el archivo subido; solo se usa para volver a escalar.
- Al cambiar solo el alto, el navegador descarga el original desde
  `GET /admin/cards/{card}/model/original` (permiso `cards.edit`), lo vuelve a
  escalar y sube solo el archivo escalado. **Nunca se escala un archivo ya escalado.**
- Modelos subidos antes de esta función (sin original): el archivo actual se
  considera el original. Al escalarlos por primera vez, el servidor copia ese
  archivo al disco privado antes de reemplazarlo.
- La API (`CardResource`) devuelve `model_url` (archivo escalado) y
  `model_dimensions: { height, width, depth }` en metros, o `null` si no hay
  modelo o es un modelo anterior sin tamaño definido.

Código principal:

| Parte | Archivo |
| --- | --- |
| Escalado en el navegador | `resources/js/lib/glb/resizeGlb.ts` |
| Estado del formulario | `resources/js/composables/useCardModelSize.ts` |
| Panel "Tamaño real" | `resources/js/components/admin/cards/ModelSizeField.vue` |
| Vista previa con referencia | `resources/js/components/admin/cards/ModelViewer.vue`, `resources/js/lib/glb/sizeStage.ts` |
| Validación en PHP | `app/Services/GlbInspector.php`, `app/Rules/GlbFile.php`, `app/Concerns/CardModelValidationRules.php` |
| Configuración | `config/glb.php` |

## Flujo del administrador

1. En **Modelo 3D**, elige el archivo `.glb`. Aparece el panel **Tamaño real**
   (con un esqueleto de carga mientras se mide el archivo).
2. En **Alto final**, escribe el alto en **metros**: `1`, `1.5`, `0.75`, `0.05`…
   Se acepta punto o coma decimal (`1,5`) y hasta 2 decimales. También puede:
   - usar los botones **−/+** (±0.1 m, o ±0.01 m por debajo de 0.5 m);
   - elegir una medida rápida con un animal representativo del Zoológico
     Sahuatoba (alto total aproximado de un adulto, del suelo a la cabeza,
     orejas o joroba): 0.05 m (rana de White), 0.15 (tortuga de Mapimí),
     0.25 (iguana verde), 0.4 (mapache, cocodrilo), 0.5 (zorro gris, gato
     montés), 0.75 (coyote, búho real), 0.9 (lobo mexicano, jaguar), 1 (oso
     negro), 1.25 (león, tigre de Bengala), 1.5 (venado cola blanca,
     hipopótamo), 1.7 (cebra, llama), 2 (bisonte americano, eland) o 2.5 m
     (dromedario, avestruz). Son referencias: usa la medida real del ejemplar
     si la conoces. Los animales bajos y largos (cocodrilo, serpientes) tienen
     poco alto aunque sean largos;
   - pulsar **Usar tamaño del archivo** para conservar el tamaño original.
3. Revisa las tarjetas **Alto / Ancho / Largo** (original → final) y la escala (×0.41).
4. En la vista previa, compara con la referencia: **Persona 1.70 m**, **Cubo 1 m**
   o **Ninguna**. La cuadrícula del suelo tiene líneas cada 1 m y la etiqueta
   de la esquina superior derecha muestra el alto final.
5. Guarda. La barra inferior muestra "Procesando modelo…" y luego "Subiendo N%".

En **Editar**, el campo ya trae el alto guardado. "Modelo actual" muestra el
alto ("1.50 m de alto") o "Sin tamaño definido" en modelos anteriores. Si solo
cambia el alto, se indica "1.00 m → 1.50 m · se volverá a escalar desde el
archivo original".

Las advertencias (en ámbar) no impiden guardar: extensiones no compatibles,
modelo con esqueleto, archivo de más de 20 m (probablemente exportado en
centímetros) o escala extrema (más de ×100 o menos de ×0.01).

## Contrato del origen

En el archivo escalado:

- `[0, 0, 0]` es el **centro inferior** de la caja del modelo: centro en X y Z,
  Y mínima.
- +Y hacia arriba; el modelo se apoya en Y = 0.
- La escena tiene un único nodo raíz `ScaleRoot` con escala uniforme y sin
  rotación. Los vértices no se modifican (animaciones y esqueletos siguen
  funcionando).

Tolerancias del servidor (`config/glb.php`): alto ±1 %, origen
`max(1 mm, 0.1 % del tamaño)`. El navegador verifica con ±0.1 % en el alto.

**En el juego (ViroReact):** coloca el modelo con `position` en el punto del
ancla / plano detectado, `scale={[1, 1, 1]}` y **sin desplazamientos extra**.
El modelo quedará apoyado sobre el plano y centrado en el punto tocado.

### Animaciones con desplazamiento (root motion)

El tamaño y el origen se miden en la pose de reposo. Si una animación mueve el
nodo raíz del modelo (caminar hacia adelante, saltar…), el modelo se alejará
del origen mientras se reproduce. Es el comportamiento esperado: el ancla
sigue siendo la posición de reposo.

### Modelos con esqueleto (skinning)

En glTF, la transformación del nodo de una malla con piel se ignora; el tamaño
depende de las articulaciones. El navegador mide estos modelos en su **pose de
enlace** (matrices mundiales de las articulaciones × matrices inversas de
enlace). El servidor no lee los vértices, así que en estos modelos **solo valida
la estructura** y devuelve una advertencia; se guardan las medidas calculadas
por el navegador. Revisa el tamaño en la vista previa.

Lo mismo ocurre si una pieza del modelo está rotada en un ángulo que no es
múltiplo de 90°: la caja que el servidor calcula con los valores min/max sería
mayor que la real, así que valida solo la estructura y avisa.

## Política de extensiones

| Extensión | Navegador | Servidor |
| --- | --- | --- |
| `KHR_draco_mesh_compression`, `EXT_meshopt_compression`, `KHR_mesh_quantization` | Se decodifican y se **quitan** | **Rechaza** el archivo si aún las tiene |
| Las de `config('glb.supported_extensions')` | Sin aviso | Sin aviso |
| Cualquier otra | **Advertencia** | **Advertencia** (nunca bloquea) |

La lista `supported_extensions` de `config/glb.php` es una **suposición
inicial** (`KHR_materials_unlit`, `KHR_texture_transform`,
`KHR_materials_emissive_strength`). Ajústala después de probar modelos reales
en ViroReact. El panel recibe la misma lista como prop de Inertia
(`glbConfig.supportedExtensions`), así que basta con editar el archivo de
configuración (y ejecutar `php artisan config:clear` si la configuración está
en caché).

## Requisitos del servidor

- **No se usa Node.js en producción.** Node solo se usa al compilar (Vite).
  Todo el procesamiento 3D ocurre en el navegador del administrador; PHP solo
  lee el encabezado y el JSON del GLB (sin `proc_open` ni binarios extra).
  Funciona en hosting compartido.
- **SSR es opcional.** El código 3D solo se carga con `import()` dinámico en el
  navegador. Si el servidor SSR de Inertia no está activo, Inertia renderiza
  en el cliente.
- Límites de subida (por defecto, archivos de hasta 50 MB; ver
  `glb.max_file_size`). Al guardar se envían el original **y** el escalado,
  más la imagen de la tarjeta:
  - `upload_max_filesize` ≥ tamaño máximo del GLB (p. ej. `50M`);
  - `post_max_size` ≥ 2 × GLB máximo + imagen de la tarjeta (p. ej. `120M`);
  - nginx: `client_max_body_size` ≥ `post_max_size` (p. ej. `120m`);
  - `max_execution_time` suficiente para subidas lentas.

## Desarrollo

- Archivos de prueba: `tests/Fixtures/glb` (se generan con
  `node tests/Fixtures/glb/generate.mjs`; los resultados se guardan en el repositorio).
- Pruebas del navegador: `npm run test:js` (Vitest).
- Pruebas de PHP: `php artisan test --filter=Glb` y `CardAdminTest`.
