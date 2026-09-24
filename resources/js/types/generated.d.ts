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
export type EstadoAuditoria = 'planificada' | 'en_curso' | 'cerrada';
export type ResultadoPunto = 'pendiente' | 'conforme' | 'no_conforme' | 'observacion' | 'fuera_de_muestra';
export type TipoAuditoria = 'interna' | 'externa' | 'autoevaluacion';
export type TipoHallazgo = 'nc_mayor' | 'nc_menor' | 'observacion' | 'oportunidad_mejora';
}
}
namespace Autorizacion {
namespace Enums {
export type Permiso = 'panel.ver' | 'contexto.ver' | 'contexto.gestionar' | 'contexto.aprobar' | 'sistemas.ver' | 'sistemas.gestionar' | 'sistemas.valorar' | 'implantaciones.ver' | 'implantaciones.gestionar' | 'evidencias.ver' | 'evidencias.gestionar' | 'activos.ver' | 'activos.gestionar' | 'riesgos.ver' | 'riesgos.gestionar' | 'riesgos.aceptar' | 'tareas.ver' | 'tareas.gestionar' | 'auditorias.ver' | 'auditorias.gestionar' | 'no_conformidades.ver' | 'no_conformidades.gestionar' | 'no_conformidades.verificar' | 'mejoras.ver' | 'mejoras.gestionar' | 'indicadores.ver' | 'indicadores.gestionar' | 'objetivos.ver' | 'objetivos.gestionar' | 'objetivos.aprobar' | 'personas.ver' | 'personas.gestionar' | 'personas.designar' | 'incidentes.ver' | 'incidentes.gestionar' | 'continuidad.ver' | 'continuidad.gestionar' | 'continuidad.aprobar' | 'conformidad.ver' | 'conformidad.gestionar' | 'revision_direccion.ver' | 'revision_direccion.gestionar' | 'revision_direccion.aprobar' | 'documentos.ver' | 'documentos.generar' | 'documentos.aprobar' | 'documentos.redactar' | 'documentos.plantillas' | 'organizacion.gestionar' | 'calendario.ver' | 'obligaciones.ver' | 'obligaciones.gestionar';
export type Rol = 'responsable_seguridad' | 'tecnico' | 'auditor';
}
}
namespace Aviso {
export type Fuente = 'tarea' | 'evidencia' | 'documento' | 'formacion' | 'indicador' | 'implantacion' | 'obligacion' | 'prueba_continuidad' | 'bia';
export type Vencimiento = {
readonly url: string,
readonly icono: string,
readonly id: number,
readonly fuente: App.Domain.Aviso.Fuente,
readonly titulo: string,
readonly dia: string,
readonly fecha: string,
readonly dias: number,
readonly responsable: string | null,
readonly tono: string,
readonly estadoTono: string,
readonly estadoEtiqueta: string,
};
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
namespace Conformidad {
namespace Enums {
export type EstadoConformidad = 'en_preparacion' | 'declarada' | 'publicada' | 'retirada';
export type ViaConformidad = 'declaracion' | 'certificacion';
}
}
namespace Contexto {
namespace Enums {
export type Ambito = 'interno' | 'externo';
export type EstadoAnalisis = 'borrador' | 'aprobado' | 'obsoleto';
export type MateriaCuestion = 'legal_regulatorio' | 'tecnologico' | 'economico' | 'organizativo' | 'social' | 'ambiental' | 'competitivo' | 'contractual';
export type NaturalezaRequisito = 'legal' | 'contractual' | 'expectativa';
export type Signo = 'favorable' | 'adverso';
export type TipoCuestion = 'fortaleza' | 'debilidad' | 'oportunidad' | 'amenaza';
export type TipoParteInteresada = 'cliente' | 'empleado' | 'direccion' | 'proveedor' | 'regulador' | 'socio' | 'sociedad' | 'accionista';
}
}
namespace Continuidad {
namespace Enums {
export type EstadoBia = 'borrador' | 'aprobado' | 'obsoleto';
export type EstadoPrueba = 'planificada' | 'realizada' | 'cancelada';
export type NivelImpacto = 'bajo' | 'medio' | 'alto' | 'muy_alto';
export type ResultadoPrueba = 'superada' | 'parcial' | 'fallida';
export type TipoPrueba = 'sobremesa' | 'simulacro' | 'tecnica' | 'completa';
export type TramoImpacto = 'impacto_4h' | 'impacto_1d' | 'impacto_3d' | 'impacto_1s' | 'impacto_1m';
}
}
namespace Documento {
namespace Enums {
export type ClasificacionDocumental = 'publico' | 'uso_interno' | 'confidencial';
export type EstadoDocumental = 'borrador' | 'en_revision' | 'aprobado' | 'rechazado' | 'obsoleto';
export type EstadoGeneracion = 'encolada' | 'generando' | 'generada' | 'fallida';
export type OrigenTexto = 'plantilla' | 'propio';
export type SeccionNarrativa = 'introduccion' | 'objeto_y_alcance' | 'metodologia' | 'nota_resumen' | 'nota_tabla' | 'nota_derivacion' | 'nota_madurez' | 'nota_exclusiones' | 'conclusiones' | 'limitaciones_propias' | 'aprobacion';
export type TipoDocumento = 'soa_iso' | 'dda_ens' | 'plan_adecuacion_ens' | 'analisis_contexto' | 'acta_revision' | 'politica' | 'norma' | 'procedimiento' | 'plan_continuidad' | 'declaracion_conformidad_ens';
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
namespace Incidente {
namespace Enums {
export type ClasificacionIncidente = 'contenido_abusivo' | 'contenido_danino' | 'obtencion_informacion' | 'intento_intrusion' | 'intrusion' | 'disponibilidad' | 'compromiso_informacion' | 'fraude' | 'vulnerable' | 'otros';
export type EstadoIncidente = 'abierto' | 'en_tratamiento' | 'resuelto' | 'cerrado';
export type PeligrosidadIncidente = 'baja' | 'media' | 'alta' | 'muy_alta' | 'critica';
}
}
namespace Mejora {
namespace Enums {
export type EstadoMejora = 'propuesta' | 'en_curso' | 'implantada' | 'descartada';
export type OrigenMejora = 'auditoria' | 'revision_direccion' | 'indicador' | 'incidente' | 'prueba_continuidad' | 'propia';
}
}
namespace Metrica {
namespace Enums {
export type CalculoIndicador = 'cumplimiento_implantado' | 'implantaciones_pendientes' | 'implantadas_sin_evidencia' | 'madurez_media' | 'evidencias_caducadas' | 'tareas_vencidas' | 'tareas_sin_responsable' | 'no_conformidades_abiertas' | 'no_conformidades_sin_verificar' | 'riesgos_sobre_umbral' | 'activos_sin_cifrar' | 'activos_sin_revisar' | 'personal_formado';
export type CumplimientoIndicador = 'en_objetivo' | 'fuera_de_objetivo' | 'sin_objetivo' | 'sin_medir';
export type OrigenMedicion = 'calculado' | 'manual';
export type Periodicidad = 'mensual' | 'trimestral' | 'semestral' | 'anual';
export type SentidoIndicador = 'mayor_mejor' | 'menor_mejor';
export type UnidadIndicador = 'porcentaje' | 'recuento' | 'dias' | 'euros';
}
}
namespace NoConformidad {
namespace Enums {
export type EstadoNoConformidad = 'abierta' | 'en_tratamiento' | 'cerrada' | 'verificada' | 'anulada';
export type OrigenNoConformidad = 'auditoria' | 'incidente' | 'revision_direccion' | 'prueba_continuidad' | 'propia';
}
}
namespace Objetivo {
namespace Enums {
export type EstadoObjetivo = 'propuesto' | 'aprobado' | 'alcanzado' | 'no_alcanzado' | 'retirado';
}
}
namespace Obligacion {
export type Referencia = {
readonly tipo: App.Domain.Obligacion.Enums.ReferenciaCumplimiento,
readonly id: number,
readonly etiqueta: string,
readonly icono: string,
readonly url: string,
};
namespace Enums {
export type ReferenciaCumplimiento = 'auditoria' | 'revision_direccion' | 'documento' | 'prueba_continuidad';
}
}
namespace Organizacion {
namespace Marca {
export type PiezaDeMarca = 'logo' | 'simbolo';
}
}
namespace Persona {
namespace Enums {
export type RolEns = 'responsable_informacion' | 'responsable_servicio' | 'responsable_seguridad' | 'responsable_sistema' | 'administrador_seguridad';
export type TipoAccionFormativa = 'formacion' | 'concienciacion';
export type TipoPasoPersona = 'alta' | 'baja';
}
}
namespace RevisionDireccion {
namespace Enums {
export type EstadoRevision = 'planificada' | 'en_curso' | 'aprobada';
}
}
namespace Riesgo {
namespace Enums {
export type DecisionRiesgo = 'mitigar' | 'aceptar' | 'transferir' | 'evitar';
export type GrupoAmenaza = 'desastres_naturales' | 'origen_industrial' | 'errores_no_intencionados' | 'ataques_intencionados';
export type NivelRiesgo = 'muy_bajo' | 'bajo' | 'medio' | 'alto' | 'muy_alto';
}
}
namespace Sistema {
namespace Enums {
export type EstadoSistema = 'borrador' | 'activo' | 'archivado';
}
}
namespace Tarea {
namespace Enums {
export type EstadoTarea = 'pendiente' | 'en_curso' | 'bloqueada' | 'hecha' | 'descartada';
export type OrigenTarea = 'hallazgo' | 'no_conformidad' | 'mejora' | 'riesgo' | 'brecha_implantacion' | 'contexto' | 'objetivo' | 'incidente' | 'revision_direccion' | 'continuidad' | 'propia';
export type PrioridadTarea = 'baja' | 'media' | 'alta' | 'critica';
}
}
namespace Traza {
namespace Enums {
export type AccionAuditada = 'creado' | 'actualizado' | 'eliminado';
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
secundaria: boolean,
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
readonly icono: string | null,
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
namespace Metrica {
export type PuntoSerie = {
readonly periodo: string,
readonly etiqueta: string,
readonly valor: number,
readonly valorEscrito: string,
readonly objetivo: number | null,
readonly objetivoEscrito: string | null,
readonly fraccion: string | null,
readonly cumplimiento: string,
readonly cumplimientoEtiqueta: string,
readonly tono: string,
readonly icono: string,
readonly origen: string,
readonly nota: string | null,
};
}
namespace Panel {
export type AvanceMarco = {
readonly codigo: string,
readonly nombre: string,
readonly aplicables: number,
readonly implantadas: number,
};
export type Indicador = {
readonly clave: string,
readonly etiqueta: string,
readonly valor: number,
readonly tono: string,
readonly filtro: string,
readonly base: string,
readonly ayuda: string | null,
};
export type ProximaObligacion = {
readonly id: number,
readonly titulo: string,
readonly fecha: string,
readonly dias: number,
readonly cuando: string,
};
export type Reparto = {
readonly clave: string,
readonly etiqueta: string,
readonly valor: number,
readonly tono: string,
readonly filtro: string | null,
};
export type ResumenContextoPanel = {
readonly analisisVigente: string | null,
readonly fechaAnalisis: string | null,
readonly mesesDesdeElAnalisis: number | null,
readonly hayBorrador: boolean,
readonly cuestiones: number,
readonly porTipo: App.Http.Resources.Panel.Reparto[],
readonly sinRiesgo: number,
readonly partes: number,
readonly requisitosQueObligan: number,
readonly obligacionesSinCubrir: number,
readonly climaPertinente: boolean | null,
};
export type ResumenEvidencias = {
readonly total: number,
readonly caducadas: number,
readonly porCaducar: number,
readonly implantadasSinEvidencia: number,
};
export type ResumenIncidentesPanel = {
readonly total: number,
readonly abiertos: number,
readonly fueraDePlazoAepd: number,
readonly enPlazoAepd: number,
readonly sinLeccion: number,
readonly porEstado: App.Http.Resources.Panel.Reparto[],
};
export type ResumenInventarioPanel = {
readonly vigentes: number,
readonly resueltos: number,
readonly restringidos: number,
readonly cifrado: App.Http.Resources.Panel.Reparto[],
readonly copia: App.Http.Resources.Panel.Reparto[],
readonly porTipo: App.Http.Resources.Panel.Reparto[],
readonly porCicloDeVida: App.Http.Resources.Panel.Reparto[],
};
export type ResumenMetricasPanel = {
readonly total: number,
readonly activos: number,
readonly periodoSinMedir: number,
readonly nuncaMedidos: number,
readonly fueraDeObjetivo: number,
readonly porCumplimiento: App.Http.Resources.Panel.Reparto[],
};
export type ResumenNoConformidadesPanel = {
readonly total: number,
readonly abiertas: number,
readonly vencidas: number,
readonly sinVerificar: number,
readonly sinAccion: number,
readonly porEstado: App.Http.Resources.Panel.Reparto[],
};
export type ResumenObjetivosPanel = {
readonly total: number,
readonly vivos: number,
readonly vencidos: number,
readonly sinIndicador: number,
readonly sinActuacion: number,
readonly porEstado: App.Http.Resources.Panel.Reparto[],
};
export type ResumenObligacionesPanel = {
readonly total: number,
readonly vencidas: number,
readonly porVencer: number,
readonly nuncaCumplidas: number,
readonly sinResponsable: number,
readonly proxima: App.Http.Resources.Panel.ProximaObligacion | null,
};
export type ResumenPanel = {
readonly sistemas: number,
readonly aplicables: number,
readonly implantadas: number,
readonly pendientes: number,
readonly madurezMedia: number | null,
readonly madurezEvaluadas: number,
};
export type ResumenPersonasPanel = {
readonly total: number,
readonly activas: number,
readonly sinFormacion: number,
readonly sinAcuerdo: number,
readonly bajaSinCerrar: number,
readonly rolesDesignados: number,
readonly rolesExigibles: number,
};
export type ResumenPlanPanel = {
readonly total: number,
readonly abiertas: number,
readonly vencidas: number,
readonly sinResponsable: number,
readonly porEstado: App.Http.Resources.Panel.Reparto[],
readonly porPrioridad: App.Http.Resources.Panel.Reparto[],
readonly porOrigen: App.Http.Resources.Panel.Reparto[],
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
export type VistaPanel = {
readonly clave: string,
readonly etiqueta: string,
readonly href: string,
readonly pregunta: string,
readonly alertas: number,
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
