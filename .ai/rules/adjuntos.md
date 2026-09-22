---
paths:
  - app/Domain/Adjunto/**
  - resources/js/components/adjunto/**
---

# Los adjuntos

La documentación que cuelga de un registro. Hasta aquí lo único posible era
**referenciar una evidencia ya existente** —`acciones_formativas.evidencia_id`,
`acuerdos_confidencialidad.evidencia_id`—, y esas dos claves **se quedan**.

**Un adjunto no es una evidencia, y la frontera es el propósito.** Una evidencia
prueba un requisito y por eso lleva caducidad, periodicidad y responsable; un
adjunto documenta un registro —el título de un curso, el contrato firmado, la hoja
de firmas escaneada— y no tiene nada de eso. Obligar a rellenar cuatro campos de
caducidad para subir un PDF es cómo se consigue que no se suba, y el diálogo lo
dice por escrito para que nadie ponga aquí lo que va allí.

**Pivotes explícitas y no una relación polimórfica.** En todo el repositorio no
hay un solo `morphTo` y esto no lo estrena: el dialecto es la pivote con nombre
—`implantacion_tarea`, `evidencia_implantacion`— y a cambio se conservan **claves
foráneas reales**, que un morph no puede tener. El tercer anfitrión entra con una
pivote y sin tocar `adjuntos`. Y es **N:M de verdad**: el certificado de un curso
documenta a la vez a quien lo hizo y a la sesión donde se impartió, y registrarlo
dos veces sería subir el mismo fichero dos veces.

**Disco propio, y de ahí sale la diferencia que define el módulo: borrar un
adjunto borra también el objeto del almacén.** El bucket de evidencias lleva
Object Lock en modo compliance —por eso `EvidenciaController::destroy()` deja el
fichero a propósito—, y con eso un DNI subido por error **no se podría borrar
nunca**. Con datos personales dentro eso es un problema y no una garantía: quien
ejerce su derecho de supresión no acepta «la fila ya no está».

Lo copiado de `RegistrarEvidencia`, porque allí ya costó: la huella se calcula del
fichero **recibido y antes de subirlo** —se mide lo que llegó, no lo que quedó en
el bucket—, el nombre en el almacén es un ULID bajo el prefijo de la organización,
y la descarga es un **redirect a URL firmada de cinco minutos**, nunca un enlace
al bucket.

**Sin verbo de permiso nuevo**: un adjunto no es un módulo, es una capacidad que
se le añade a un registro, así que las rutas cuelgan del anfitrión y heredan el
suyo — la descarga con `.ver` y la subida con `.gestionar`. `ConAdjuntos` +
`TieneAdjuntos` son el contrato; la interfaz existe porque **sin morph no hay un
tipo común**, y `adjuntosCargados()` porque la magia de Eloquent que resuelve una
relación en propiedad no cruza una interfaz.

**Sin lista de `mimes`**, igual que una evidencia: lo que hay que adjuntar no lo
decide esta herramienta, y una lista blanca corta acaba en gente renombrando
extensiones. El bucket es privado y la descarga va con
`Content-Disposition: attachment`, así que nada se sirve en línea.

Dos cosas que se probaron y se quitaron:

- **El `CHECK (tamano > 0)`.** Un fichero vacío es un error de quien lo sube, no
  una incoherencia de los datos, y con la restricción puesta lo que ve esa persona
  es un 500 hablando de una restricción de PostgreSQL.
- **Un composable que desplazara hasta el bloque** al llegar desde el menú «…» con
  `#adjuntos`. El `preserveScroll` del `DataTable` —que está ahí para que las demás
  acciones de fila no salten al principio— gana a cualquier `scrollIntoView` al
  montar. **El ancla marca y no desplaza**, y queda dicho: un composable que no
  hace lo que promete es peor que no tenerlo.

### Lo que este módulo declara que no hace todavía

- **No versiona un adjunto**: se sube otro y se borra el anterior.
- **No busca dentro del fichero** ni extrae texto.
- **No comprueba que lo subido corresponda** al registro donde se cuelga: que el
  título adjuntado a una formación sea de esa formación no lo mira nadie.
- **No hay pantalla propia de adjuntos** ni recuento en el panel: se ven desde la
  ficha de su anfitrión y no hay «todos los documentos de la organización».
- **Un adjunto sin anfitrión no existe por construcción** —se crea vinculado—,
  pero **desvincular no está expuesto**: lo que la interfaz ofrece es borrar.
