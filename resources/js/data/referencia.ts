export const TIPOS_DOCUMENTO = ['AS','CC','CE','CN','MS','PA','PE','PT','RC','SC','TI'];

export const EPS_LIST = [
  'ADRES','AIC-ASOCIACION INDIGENA DEL CAUCA','ARL COLMENA','ARL LA EQUIDAD',
  'ARL LA PREVISORA','ARL SURAMERICANA','ASMET SALUD EVENTO','ASMET SALUD PP',
  'AXA COLPATRIA','CAPITAL SALUD','CAPRECOM','COMFACHOCO','COMFENALCO','COMPENSAR',
  'COOSALUD','COSMITET LTDA VALLE','DISPENSARIO MEDICO','EJERCITO NACIONAL',
  'EMSSANAR EVENTO','EMSSANAR PGP','FAMISANAR','FIDUCIARIA LA PREVISORA (INPEC)',
  'FOMAG','NUEVA EPS EVENTO','NUEVA EPS PGP','OTRAS','PARTICULAR','POLIZA',
  'POSITIVA ARL','SALUD TOTAL','SANIDAD MILITAR','SANIDAD POLICIA','SANITAS EPS',
  'SAVIA SALUD','SECRETARIA DEPARTAMENTAL DE SALUD','SEGUROS BOLIVAR','SOAT','SOS',
  'SURA EVENTO','SUSALUD EPS','UNIDAD PRESTADORA DE SALUD DEL VALLE DEL CAUCA',
];

/**
 * EPS que tienen más de un contrato (NUEVA EPS PGP / NUEVA EPS EVENTO…).
 * Las clínicas externas eligen solo la EPS; el contrato lo define el personal
 * de referencia al aceptar o negar, escogiendo la variante completa de EPS_LIST.
 */
export const EPS_CON_CONTRATO = ['ASMET SALUD', 'EMSSANAR', 'NUEVA EPS', 'SURA'];

/** Nombre de la EPS sin el contrato: 'NUEVA EPS PGP' → 'NUEVA EPS'. */
export function epsBase(eps: string | null | undefined): string {
  const nombre = (eps ?? '').trim();
  return EPS_CON_CONTRATO.find((base) => nombre === base || nombre.startsWith(`${base} `)) ?? nombre;
}

/** Lista para clínicas externas: cada EPS una sola vez, sin PGP / Evento. */
export const EPS_LIST_EXTERNA = Array.from(new Set(EPS_LIST.map(epsBase)));

export const ESPECIALIDADES = [
  'AYUDA DIAGNOSTICA','CARDIOLOGIA','CIRUGIA CARDIOVASCULAR','CIRUGIA DE CABEZA Y CUELLO',
  'CIRUGIA DE COLUMNA','CIRUGIA DE MANO','CIRUGIA DE MIEMBRO SUPERIOR','CIRUGIA DE PELVIS',
  'CIRUGIA DE PIE Y TOBILLO','CIRUGIA DE TRAUMA Y EMERGENCIAS','CIRUGIA GASTROINTESTINAL',
  'CIRUGIA GENERAL','CIRUGIA HEPATOBILIAR','CIRUGIA MAXILOFACIAL','CIRUGIA ONCOLOGICA',
  'CIRUGIA PEDIATRICA','CIRUGIA PLASTICA','CIRUGIA RECONSTRUCTIVA','CIRUGIA TORAX',
  'CIRUGIA VASCULAR','COLOPROCTOLOGIA','CUIDADOS PALIATIVOS','DERMATOLOGIA',
  'ELECTROFISIOLOGIA','ENDOCRINOLOGIA','FONOAUDIOLOGIA','GASTROENTEROLOGIA','GERIATRIA',
  'GINECOLOGIA','GINECOLOGIA Y OBSTETRICIA','GRUPO DE FALLA INTESTINAL','HEMATOLOGIA',
  'HEMATOONCOLOGIA','HEMODINAMIA/ANGIOGRAFIA','HEPATOLOGIA','INFECTOLOGIA',
  'LARINGOLOGIA Y VIA AEREA','MASTOLOGIA','MEDICINA FAMILIAR','MEDICINA GENERAL',
  'MEDICINA INTERNA','NEFROLOGIA','NEFROLOGIA PEDIATRICA','NEONATOLOGIA','NEUMOLOGIA',
  'NEUROCIRUGIA','NEUROINTERVENCIONISTA','NEUROLOGIA','ODONTOLOGIA','OFTALMOLOGIA',
  'ONCOLOGIA','ORTOPEDIA','ORTOPEDIA RECONSTRUCTIVA','ORTOPEDIA PEDIATRICA',
  'OTORRINOLARINGOLOGIA','PEDIATRIA','PERINATOLOGIA','PSICOLOGIA','PSIQUIATRIA',
  'RADIOLOGIA INTERVENCIONISTA','RESONANCIA MAGNETICA','REUMATOLOGIA','TERAPIA ECMO',
  'TOXICOLOGIA CLINICA','TRABAJO SOCIAL','UNIDAD DE QUEMADOS','UROLOGIA',
  'UROLOGIA ONCOLOGICA',
];

export const SERVICIOS = [
  'URGENCIAS','HOSPITALIZACION','UCI','UCIN',
];

// Catálogo real de "Servicio que refiere" en Gomedisys (campo Kendo
// idServiceReferral) — se usa textualmente en el formulario de Nueva
// solicitud para que el valor que se guarda ya coincida exacto con lo que
// espera Gomedisys, y el envío automático no falle por texto que no
// existe en su catálogo (ver rpa-gomedisys/extension/content-gomedisys.js).
export const SERVICIOS_REMISION_GOMEDISYS = [
  'Acondicionamiento Fisico','Alergologia','Alteraciones del Adulto','Anestesia CE',
  'Anestesiologia Cardiovascular','Angiografía','Atencion Preventiva Salud Oral, Higiene Oral',
  'Cardiologia','Cardiologia Pediatrica','Centro de Atencion en Drogradiccion',
  'Centros DIA Para Rehabilitacion','Centros o Servicios Unidades de Rehabilitacion',
  'Centros y Servicios de Proteccion','Cirugía  Hepatobiliar','Cirugia Cardiovascular',
  'Cirugía Cardiovascular CE','Cirugia de Cabeza y Cuello','Cirugia de Mama y Tumores Tejidos Blandos',
  'Cirugia de Mano','Cirugía de Mano CE','Cirugia de Torax','Cirugía de Tórax CE',
  'Cirugia Dermatologica','Cirugia Endovascular Neurologia','Cirugia Gastrointestinal',
  'Cirugía Gastrointestinal CE','Cirugia Gastrointestinal Endoscopia Digestiva','Cirugia General',
  'Cirugía General CE','Cirugia Ginecologica','Cirugia Ginecologica Laparoscopica',
  'Cirugia Maxilofacial','Cirugía Maxilofacial CE','Cirugia Oftalmologica',
  'Cirugia Oncologica Pediatrica','Cirugia Oral','Cirugia Ortopedia','Cirugia Otorrinolaringologia',
  'Cirugia Pediatrica','Cirugia Plastica Facial','Cirugia Plastica Oncologica',
  'Cirugia Plastica y Estetica','Cirugia Plastica y Estetica CE','Cirugia Urologica',
  'Cirugia Vascular','Cirugia Vascular y Angiologica','Coloproctologia','Consulta Prioritaria',
  'Cuidado Intensivo Adultos','Cuidado Intermedio Adulto','Cuidado Intermedio Para Rehabilitacion',
  'Dermatologia','Dermatologia Oncologica','Diagnostico Vascular','Dolor y Cuidado Paliativos',
  'Ecocardiografia del Adulto','Electrodiagnostico','Electrofisiologia, Marcapasos y Arritmias',
  'Endocrinologia','Endodoncia','Endoscopia Digestiva','Enfermeria','Esterilizacion',
  'Estomatologia','Fisiatria','Fisioterapia','Fonoaudiologia y/o Terapia de Lenguaje',
  'Gastroenterologia y/o Endoscopia Digestiva','General Adultos','General Pediatrica','Genetica',
  'Geriatria','Gerontologia','Ginecobstetricia CE','Ginecologia Oncologica','Glaucoma',
  'Hematologia','Hematologia y Oncologia Clinica','Hemodinamia e Intervencionismo',
  'Hospitalizacion Adultos','Hospitalizacion Parcial','Imágenes Diagnosticas - Ionizantes',
  'Imágenes Diagnosticas - No ionizantes','Implante de Piel','Implante de Tejido Oseo',
  'Implante de Valvulas Cardiacas','Implantologia','Infectologia','Inmunologia',
  'Laboratorio Citologias Cervico','Laboratorio Clinico','Laboratorio de Citopatologia',
  'Laboratorio de Histotecnologia','Laboratorio de Patologia','Lactario -  Alimentacion',
  'Mastología (Cirugía de mama y Tumores Tejidos Blandos)','Medicina Alternativa - Terapia Alternativa',
  'Medicina Estetica','Medicina Familiar','Medicina Fisica Y del Deporte',
  'Medicina Fisica y Rehabilitacion','Medicina General','Medicina Interna',
  'Medicina Laboral - Salud Ocupacional','Medicina Nuclear','Nefrologia',
  'Nefrologia - Dialisis Renal','Nefrologia Pediatrica','Neonatologia',
  'Neumologia - Fibrobroncoscopia','Neumologia CE','Neumologia Laboratorio Funcion Pulmonar',
  'Neumologia Pediatrica','Neurocirugia','Neurocirugía','Neurocirugía CE','Neurologia',
  'Neuropediatria','Neurotologia','Nutricion y Dietetica','Odontologia General','Oftalmologia',
  'Oftalmologia Oncologica','Oncologia Clinica','Oncologia Y Hematologia Pediatrica','Optometria',
  'Ortodoncia','Ortopedia Infantil','Ortopedia Oncologica','Ortopedia Pediátrica CE',
  'ORTOPEDIA Y TRAUMATOLOGIA PEDIATRICA','Ortopedia y/o Traumatologia',
  'Ortopedia y/o Traumatología CE','Otras Cirugias','Otras Consultas de Especialidad',
  'Otros Implantes y Trasnplantes','Patologia','Patologia Oncologica','Pediatria','Periodoncia',
  'Planificacion Familiar','Programa de Gestión de Riesgo','Promocion en Salud','Psicologia',
  'Psiquiatria','Radiologia e Imagenes Diagnosticas','Radioterapia','Rehabilitacion Oncologica',
  'Rehabilitacion Oral','Reumatologia','Sala de Enf. Respiratorias Agudas','Sala de Observacion',
  'Sala de Procedimientos Menores','Sala de Reanimacion','Sala de Rehabilitacion Oral',
  'Sala de Rehabilitacion Respiratoria','Sala de Rehidratacion Oral','Sala de Yeso',
  'Sala General de Procedimientos Menores','Salud Ocupacional','Servicio Farmacéutico',
  'Servicios de Estetica','Terapia Alternativa','Terapia del Lenguaje','Terapia Ocupacional',
  'Terapia Respiratoria','Toma de Muestras Citologicas Cervico','Toma de Muestras de Lab. Clinico',
  'Toma e Interpr. Radiologias Oncologicas','Toxicologia','Trabajo Social',
  'Transfusion Sanguinea','Transplante de Corazon','Transplante de Cornea','Transplante de Higado',
  'Transplante de Pulmon','Transplante de Riñon - Pancreas','Transplante Medula Osea o Celulas Madre',
  'Transporte Asistencial Basico','Transporte Asistencial Medicalizado','Ultrasonido',
  'Unidad de Medicina Reproductiva','Urgencias','Urgencias en Salud Mental o Psiquiatria',
  'Urologia','Urologia - Litotripsia Urologica','Urologia Oncologica','Urologia Procedimiento',
  'Vacunacion',
];

export const MOTIVOS_NEGACION = [
  'DIRECCIONAMIENTO ADMINISTRATIVO','Direccionamiento CACSB','EXCLUSIÓN DEL PGP',
  'NO ACEPTADO POR MEDICO DE UCI/UCIN','NO CANDIDATA UCI/UCIN','NO CONVENIO',
  'NO CUPO EN UCI/UCIN','NO DISPONBILIDAD DE INSUMO/EQUIPO',
  'NO DISPONIBILIDAD DE CAMA EN PISO','NO DISPONIBILIDAD DE CAMA EN UCI',
  'NO ESPECIALIDAD','NO TRASLADABLE','SIN CRITERIO UCI/UCIN',
  'SIN RESPUESTA POR PARTE DE INTENSIVISTA','UBICADO EN OTRA IPS',
];

export const MOTIVOS_NO_INGRESO = [
  'N/A','Alta Medica','Alta Voluntaria','Desestimiento','Direccionamiento CACSB',
  'EPS Autoriza Estancia en IPS Actual','Fallecimiento','Medico cancela tramite',
  'No trasladable','Otros Motivos','Silencio Administrativo',
  'Supero tiempo de reserva','Ubicado en Otra IPS',
];
