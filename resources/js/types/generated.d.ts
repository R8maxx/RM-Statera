declare namespace App {
namespace Domain {
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
readonly tono: string | null,
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
namespace Panel {
export type AvanceMarco = {
readonly codigo: string,
readonly nombre: string,
readonly aplicables: number,
readonly implantadas: number,
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
}
}
}
