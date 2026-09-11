declare namespace App {
namespace Domain {
namespace Activo {
namespace Enums {
export type Clasificacion = 'publico' | 'uso_interno' | 'confidencial' | 'restringido' | 'no_aplica';
export type EstadoCicloVida = 'planificado' | 'en_stock' | 'en_produccion' | 'en_mantenimiento' | 'en_reparacion' | 'prestado' | 'retirado' | 'dado_de_baja';
export type EstadoControl = 'si' | 'no' | 'por_confirmar' | 'no_aplica';
export type TipoActivo = 'servicios' | 'datos' | 'software' | 'hardware' | 'comunicaciones' | 'soportes' | 'equipamiento_auxiliar' | 'instalaciones' | 'personal';
}
}
namespace Auditoria {
namespace Enums {
export type AccionAuditada = 'creado' | 'actualizado' | 'eliminado';
}
}
namespace Autorizacion {
namespace Enums {
export type Permiso = 'panel.ver' | 'sistemas.ver' | 'sistemas.gestionar' | 'sistemas.valorar' | 'implantaciones.ver' | 'implantaciones.gestionar' | 'evidencias.ver' | 'evidencias.gestionar' | 'activos.ver' | 'activos.gestionar' | 'documentos.ver' | 'documentos.generar' | 'documentos.redactar' | 'documentos.plantillas';
export type Rol = 'responsable_seguridad' | 'tecnico' | 'auditor';
}
}
namespace Catalogo {
namespace Enums {
export type CategoriaEns = 'basica' | 'media' | 'alta';
export type Dimension = 'C' | 'I' | 'D' | 'A' | 'T';
export type EstadoMarco = 'vigente' | 'derogado';
export type TipoCorrespondencia = 'equivalente' | 'parcial' | 'relacionado';
export type TipoRequisito = 'clausula' | 'control' | 'medida';
}
}
namespace Categorizacion {
namespace Enums {
export type NivelDimension = 'na' | 'bajo' | 'medio' | 'alto';
export type OrigenExigencia = 'categoria' | 'modulacion_dimension' | 'perfil' | 'catalogo';
}
}
namespace Documento {
namespace Enums {
export type ClasificacionDocumental = 'publico' | 'uso_interno' | 'confidencial';
export type EstadoGeneracion = 'encolada' | 'generando' | 'generada' | 'fallida';
export type OrigenTexto = 'plantilla' | 'propio';
export type SeccionNarrativa = 'introduccion' | 'objeto_y_alcance' | 'metodologia' | 'nota_resumen' | 'nota_tabla' | 'nota_derivacion' | 'nota_madurez' | 'nota_exclusiones' | 'conclusiones' | 'limitaciones_propias' | 'aprobacion';
export type TipoDocumento = 'soa_iso' | 'dda_ens';
}
}
namespace Evidencia {
namespace Enums {
export type PeriodicidadRenovacion = 'mensual' | 'trimestral' | 'semestral' | 'anual' | 'bienal';
export type TipoEvidencia = 'captura' | 'log' | 'informe' | 'contrato' | 'registro' | 'certificado';
}
}
namespace Implantacion {
namespace Enums {
export type EstadoImplantacion = 'no_iniciado' | 'planificado' | 'en_progreso' | 'implantado' | 'no_aplica';
export type NivelMadurez = 'l0' | 'l1' | 'l2' | 'l3' | 'l4' | 'l5';
}
}
namespace Sistema {
namespace Enums {
export type EstadoSistema = 'borrador' | 'activo' | 'archivado';
}
}
}
namespace Http {
namespace Resources {
namespace Definicion {
export type Accion = {
icono: string | null,
confirmacion: string | null,
destructiva: boolean,
readonly clave: string,
readonly etiqueta: string,
readonly url: string,
readonly metodo: App.Http.Resources.Enums.MetodoAccion,
};
export type Columna = {
ordenable: boolean,
ocultaPorDefecto: boolean,
anclada: boolean,
alineacion: App.Http.Resources.Enums.Alineacion,
ancho: string | null,
ayuda: string | null,
readonly clave: string,
readonly etiqueta: string,
readonly tipo: App.Http.Resources.Enums.TipoColumna,
};
export type DefinicionRecurso = {
readonly clave: string,
readonly etiquetas: App.Http.Resources.Definicion.Etiquetas,
readonly columnas: App.Http.Resources.Definicion.Columna[],
readonly filtros: App.Http.Resources.Definicion.Filtro[],
readonly accionesFila: App.Http.Resources.Definicion.Accion[],
readonly accionesMasivas: App.Http.Resources.Definicion.Accion[],
readonly accionesGenerales: App.Http.Resources.Definicion.Accion[],
readonly ordenPorDefecto: string,
readonly tamanosPagina: number[],
readonly seleccionable: boolean,
readonly accionPorDefecto: string | null,
readonly accionAlternativa: string | null,
};
export type Etiquetas = {
readonly singular: string,
readonly plural: string,
readonly descripcion: string | null,
readonly vacio: string | null,
};
export type Filtro = {
placeholder: string | null,
opciones: App.Http.Resources.Definicion.Opcion[],
multiple: boolean,
columna: string | null,
resaltaEn: string[],
readonly clave: string,
readonly etiqueta: string,
readonly tipo: App.Http.Resources.Enums.TipoFiltro,
};
export type MetaTabla = {
readonly pagina: number,
readonly porPagina: number,
readonly total: number,
readonly ultimaPagina: number,
readonly desde: number | null,
readonly hasta: number | null,
readonly orden: string,
readonly filtros: Record<string, string | string[]>,
};
export type Opcion = {
readonly valor: string,
readonly etiqueta: string,
};
export type ValorEnlace = {
readonly etiqueta: string,
readonly url: string,
readonly externo: boolean,
};
export type ValorEscala = {
readonly valor: number,
readonly de: number,
readonly etiqueta: string,
readonly corta: string | null,
};
export type ValorEtiquetado = {
readonly valor: string | number | null,
readonly etiqueta: string,
readonly tono?: string | null,
readonly icono?: string | null,
};
export type ValorProgreso = {
readonly porcentaje: number,
readonly hechas: number | null,
readonly de: number | null,
};
}
namespace Enums {
export type Alineacion = 'izquierda' | 'centro' | 'derecha';
export type MetodoAccion = 'get' | 'post' | 'put' | 'patch' | 'delete';
export type TipoColumna = 'texto' | 'numero' | 'fecha' | 'fecha_hora' | 'booleano' | 'badge' | 'enlace' | 'progreso' | 'escala';
export type TipoFiltro = 'busqueda' | 'texto' | 'select' | 'multi_select' | 'booleano' | 'rango_fechas';
}
namespace Implantacion {
export type Correspondencia = {
readonly requisitoId: number,
readonly codigo: string,
readonly titulo: string,
readonly marco: string | null,
readonly tipo: string,
readonly cubreDelTodo: boolean,
readonly nota: string | null,
readonly implantaciones: App.Http.Resources.Implantacion.EstadoCorrespondencia[],
};
export type EstadoCorrespondencia = {
readonly implantacionId: number,
readonly sistema: string,
readonly estado: string,
readonly estadoEtiqueta: string,
readonly aplica: boolean,
};
}
namespace Panel {
export type AvanceMarco = {
readonly codigo: string,
readonly nombre: string,
readonly aplicables: number,
readonly implantadas: number,
};
export type IndicadorInventario = {
readonly clave: string,
readonly etiqueta: string,
readonly valor: number,
readonly tono: string,
readonly filtro: string,
readonly ayuda: string | null,
};
export type RepartoInventario = {
readonly clave: string,
readonly etiqueta: string,
readonly valor: number,
readonly tono: string,
readonly filtro: string | null,
};
export type ResumenEvidencias = {
readonly total: number,
readonly caducadas: number,
readonly porCaducar: number,
readonly implantadasSinEvidencia: number,
};
export type ResumenInventarioPanel = {
readonly vigentes: number,
readonly resueltos: number,
readonly restringidos: number,
readonly cifrado: App.Http.Resources.Panel.RepartoInventario[],
readonly copia: App.Http.Resources.Panel.RepartoInventario[],
readonly porTipo: App.Http.Resources.Panel.RepartoInventario[],
readonly porCicloDeVida: App.Http.Resources.Panel.RepartoInventario[],
};
export type ResumenPanel = {
readonly sistemas: number,
readonly aplicables: number,
readonly implantadas: number,
readonly pendientes: number,
readonly madurezMedia: number | null,
readonly madurezEvaluadas: number,
};
export type SegmentoEstado = {
readonly clave: string,
readonly etiqueta: string,
readonly valor: number,
};
export type SistemaResumido = {
readonly id: number,
readonly codigo: string,
readonly nombre: string,
readonly marco: string | null,
readonly categoria: string | null,
readonly aplicables: number,
readonly implantadas: number,
};
}
namespace Valoracion {
export type CambioExigencia = {
readonly codigo: string,
readonly anterior: string,
readonly nueva: string,
};
export type PrevisualizacionValoracion = {
readonly categoria: string | null,
readonly enAmbitoEns: boolean,
readonly hayCambios: boolean,
readonly creadas: string[],
readonly reactivadas: string[],
readonly dejanDeAplicar: string[],
readonly cambianExigencia: App.Http.Resources.Valoracion.CambioExigencia[],
readonly sinCambios: number,
};
}
}
}
}
