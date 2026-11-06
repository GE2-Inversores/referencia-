import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

/**
 * Genera el PDF del informe de referencias para el comité. Igual que
 * exportarSolicitudPdf: se arma en contenedores ocultos de la misma página
 * (todo el texto dinámico escapado), se rasteriza con html2canvas y se
 * exporta con jsPDF. Cada bloque (resumen, tablas, trozos de tabla) se
 * renderiza por separado para no cortar una fila a la mitad entre páginas.
 */

export interface InformeDatos {
  periodo: { desde: string; hasta: string };
  generado_en: string;
  resumen: Record<string, number>;
  por_estado: { label: string; total: number; pct: number }[];
  grupos_negacion: { label: string; total: number; pct: number }[];
  motivos_negacion: { grupo: string; motivo: string; total: number; pct: number }[];
  motivos_no_ingreso: { motivo: string; total: number; pct: number }[];
  por_eps: FilaDesglose[];
  por_clinica: FilaDesglose[];
  por_especialidad: FilaDesglose[];
  tiempo_respuesta: { respondidas: number; promedio_min: number | null; mediana_min: number | null };
  detalle_aceptadas: {
    total: number;
    aceptado: number;
    completadas: number;
    por_eps: FilaConteo[];
    por_sede: FilaConteo[];
    por_servicio: FilaConteo[];
    por_especialidad: FilaConteo[];
  };
  detalle_negadas: { por_eps: FilaConteo[]; por_especialidad: FilaConteo[]; por_clinica: FilaConteo[] };
  casos_aceptados: CasoNegado[];
  casos_ingresos: CasoNegado[];
  casos_no_ingreso: CasoNegado[];
  casos_negados: CasoNegado[];
}

export interface FilaConteo {
  nombre: string;
  total: number;
  aceptado: number;
  completadas: number;
  pct: number;
}

export interface FilaDesglose {
  nombre: string;
  total: number;
  aceptadas: number;
  negadas: number;
  canceladas: number;
  pendientes: number;
  tasa_aceptacion: number;
}

export interface CasoNegado {
  id: number;
  fecha: string;
  paciente: string;
  documento: string;
  eps: string;
  institucion: string | null;
  especialidad: string;
  grupo_negacion: string | null;
  motivo_negacion: string | null;
  motivo_no_ingreso: string | null;
  observaciones: string | null;
  estado: string;
  sede: string | null;
  servicio_llegada: string | null;
  codigo_aceptacion: string | null;
  numero_ingreso: string | null;
}

const ANCHO = 780;

function esc(valor: unknown): string {
  return String(valor ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c] as string));
}

export function formatearMinutos(min: number | null | undefined): string {
  if (min === null || min === undefined) return '—';
  if (min < 60) return `${min} min`;
  const h = Math.floor(min / 60);
  const m = min % 60;
  return m ? `${h} h ${m} min` : `${h} h`;
}

function fechaLarga(iso: string): string {
  const d = new Date(`${iso.slice(0, 10)}T00:00:00`);
  return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' });
}

const TITULO = 'font-size:.8rem; font-weight:800; color:#0d2d5e; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .6rem; padding-bottom:.4rem; border-bottom:2px solid #e2e8f0;';
const TH = 'text-align:left; padding:.4rem .5rem; background:#16468e; color:#fff; font-size:.68rem; font-weight:700;';
const TD = 'padding:.38rem .5rem; border-bottom:1px solid #e2e8f0; font-size:.72rem; color:#334155; vertical-align:top;';

function envolver(contenido: string): string {
  return `<div style="font-family:'Segoe UI',Arial,sans-serif; color:#1e293b; background:#fff; padding:.4rem 1.5rem; width:${ANCHO}px; box-sizing:border-box;">${contenido}</div>`;
}

function tarjeta(etiqueta: string, valor: string | number, detalle: string, color: string): string {
  return `<div style="flex:1; border:1px solid #e2e8f0; border-top:4px solid ${color}; border-radius:10px; padding:.7rem .8rem; background:#f8fafc;">
    <div style="font-size:.62rem; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:.04em;">${esc(etiqueta)}</div>
    <div style="font-size:1.5rem; font-weight:800; color:#0d2d5e; line-height:1.2;">${esc(valor)}</div>
    <div style="font-size:.64rem; color:#64748b;">${esc(detalle)}</div>
  </div>`;
}

function barra(pct: number, color: string): string {
  return `<div style="height:6px; width:90px; background:#e2e8f0; border-radius:99px; display:inline-block; vertical-align:middle;"><div style="height:6px; width:${Math.min(100, Math.max(0, pct))}%; background:${color}; border-radius:99px;"></div></div>`;
}

/** Parte una tabla en bloques de `porBloque` filas para que ninguna fila quede cortada entre páginas. */
function tabla(titulo: string, columnas: string[], filas: string[][], porBloque: number, vacio = 'Sin registros en el período.'): string[] {
  if (filas.length === 0) {
    return [envolver(`<h2 style="${TITULO}">${esc(titulo)}</h2><p style="font-size:.74rem; color:#94a3b8;">${esc(vacio)}</p>`)];
  }

  const bloques: string[] = [];
  for (let i = 0; i < filas.length; i += porBloque) {
    const trozo = filas.slice(i, i + porBloque);
    bloques.push(envolver(`
      <h2 style="${TITULO}">${esc(titulo)}${i > 0 ? ' (continuación)' : ''}</h2>
      <table style="width:100%; border-collapse:collapse;">
        <thead><tr>${columnas.map((c) => `<th style="${TH}">${esc(c)}</th>`).join('')}</tr></thead>
        <tbody>${trozo.map((f) => `<tr>${f.map((c) => `<td style="${TD}">${c}</td>`).join('')}</tr>`).join('')}</tbody>
      </table>`));
  }
  return bloques;
}

function construirBloques(d: InformeDatos): string[] {
  const r = d.resumen;
  const bloques: string[] = [];

  bloques.push(envolver(`
    <div style="background:linear-gradient(125deg,#0d2d6b,#16468e); border-radius:14px; padding:1.2rem 1.4rem; color:#fff; margin-bottom:1rem;">
      <h1 style="font-size:1.15rem; font-weight:800; margin:0;">Informe de gestión de referencias</h1>
      <p style="font-size:.78rem; opacity:.85; margin:.35rem 0 0;">Período: ${esc(fechaLarga(d.periodo.desde))} al ${esc(fechaLarga(d.periodo.hasta))}</p>
      <p style="font-size:.66rem; opacity:.65; margin:.2rem 0 0;">Clínica CAC Santa Bárbara · Central de Referencia · Generado el ${esc(new Date(d.generado_en).toLocaleString('es-CO'))}</p>
    </div>
    <div style="display:flex; gap:.6rem; margin-bottom:.6rem;">
      ${tarjeta('Recibidas', r.total, 'solicitudes en el período', '#3b82f6')}
      ${tarjeta('Aceptadas', r.aceptadas, `${r.tasa_aceptacion}% de las resueltas`, '#22c55e')}
      ${tarjeta('Negadas', r.negadas, `${r.tasa_negacion}% de las resueltas`, '#ef4444')}
    </div>
    <div style="display:flex; gap:.6rem;">
      ${tarjeta('Pendientes por cupo', r.pendientes_cupo, 'aceptadas en espera de cupo', '#f97316')}
      ${tarjeta('Nuevas sin respuesta', r.nuevas, 'por aceptar o negar', '#f59e0b')}
      ${tarjeta('Tiempo de respuesta', formatearMinutos(d.tiempo_respuesta.promedio_min), `mediana ${formatearMinutos(d.tiempo_respuesta.mediana_min)}`, '#8b5cf6')}
    </div>
    <p style="font-size:.66rem; color:#64748b; margin:.7rem 0 0;">Aceptadas = Aceptado + Completada. La tasa se calcula sobre las solicitudes resueltas (aceptadas + negadas). Negadas incluye ${r.negadas_no_ingreso} por no ingreso del paciente y ${r.canceladas} canceladas se cuentan aparte.</p>`));

  bloques.push(...tabla(
    'Solicitudes por estado',
    ['Estado', 'Solicitudes', '% del total', ''],
    d.por_estado.map((e) => [esc(e.label), String(e.total), `${e.pct}%`, barra(e.pct, '#3b82f6')]),
    10,
  ));

  const a = d.detalle_aceptadas;
  const filasAceptadas = (filas: FilaConteo[]) => filas.map((f) => [esc(f.nombre), String(f.total), String(f.aceptado), String(f.completadas), `${f.pct}%`, barra(f.pct, '#22c55e')]);
  const colsAceptadas = (primera: string) => [primera, 'Aceptadas', 'Aceptado', 'Completadas', '%', ''];

  bloques.push(envolver(`
    <h2 style="${TITULO}">Solicitudes aceptadas</h2>
    <div style="display:flex; gap:.6rem;">
      ${tarjeta('Aceptadas', a.total, `${r.tasa_aceptacion}% de las resueltas`, '#22c55e')}
      ${tarjeta('Aceptado', a.aceptado, 'esperando la llegada del paciente', '#3b82f6')}
      ${tarjeta('Completadas', a.completadas, 'paciente ya ingresó', '#16a34a')}
    </div>`));
  bloques.push(...tabla('Aceptadas por aseguradora', colsAceptadas('Aseguradora'), filasAceptadas(a.por_eps), 16));
  bloques.push(...tabla('Aceptadas por sede', colsAceptadas('Sede de aceptación'), filasAceptadas(a.por_sede), 12));
  bloques.push(...tabla('Aceptadas por servicio de llegada', colsAceptadas('Servicio de llegada'), filasAceptadas(a.por_servicio), 16));
  bloques.push(...tabla('Aceptadas por especialidad', colsAceptadas('Especialidad'), filasAceptadas(a.por_especialidad), 16));

  bloques.push(envolver(`
    <h2 style="${TITULO}">Resultado del ingreso</h2>
    <div style="display:flex; gap:.6rem;">
      ${tarjeta('Ingresos efectivos', r.ingresos_efectivos, 'el paciente ingresó a la clínica', '#16a34a')}
      ${tarjeta('Pendientes de ingreso', r.pendientes_ingreso, 'aceptados, esperando llegada', '#3b82f6')}
      ${tarjeta('No ingresaron', r.negadas_no_ingreso, `${r.tasa_ingreso}% de tasa de ingreso efectivo`, '#f97316')}
    </div>`));

  bloques.push(...tabla(
    'Ingresos efectivos',
    ['ID', 'Fecha', 'Paciente', 'EPS / Especialidad', 'Sede y servicio', 'Número de ingreso'],
    d.casos_ingresos.map((c) => [
      `#${c.id}`,
      esc(c.fecha),
      `${esc(c.paciente)}<br><span style="color:#94a3b8;">${esc(c.documento)}</span>`,
      `${esc(c.eps)}<br><span style="color:#94a3b8;">${esc(c.especialidad)}</span>`,
      `${esc(c.sede ?? 'Sin sede')} · ${esc(c.servicio_llegada ?? 'Sin servicio')}`,
      `<strong>${esc(c.numero_ingreso ?? '—')}</strong>`,
    ]),
    10,
    'No hubo ingresos efectivos en el período.',
  ));

  bloques.push(...tabla(
    'Pacientes que no ingresaron',
    ['ID', 'Fecha', 'Paciente', 'EPS / Institución', 'Motivo de no ingreso', 'Observaciones'],
    d.casos_no_ingreso.map((c) => [
      `#${c.id}`,
      esc(c.fecha),
      `${esc(c.paciente)}<br><span style="color:#94a3b8;">${esc(c.documento)}</span>`,
      `${esc(c.eps)}<br><span style="color:#94a3b8;">${esc(c.institucion ?? '—')}</span>`,
      `<strong>${esc(c.motivo_no_ingreso ?? '—')}</strong>`,
      esc(c.observaciones ?? ''),
    ]),
    10,
    'Todos los aceptados del período ingresaron o siguen pendientes de llegada.',
  ));

  bloques.push(...tabla(
    'Negadas por grupo',
    ['Grupo', 'Cantidad', '% de negadas por decisión', ''],
    d.grupos_negacion.map((g) => [esc(g.label), String(g.total), `${g.pct}%`, barra(g.pct, '#ef4444')]),
    10,
    'No hubo negaciones por decisión en el período.',
  ));

  bloques.push(...tabla(
    'Motivos de negación',
    ['Grupo', 'Motivo', 'Cantidad', '%', ''],
    d.motivos_negacion.map((m) => [esc(m.grupo), esc(m.motivo), String(m.total), `${m.pct}%`, barra(m.pct, '#ef4444')]),
    14,
    'No hubo negaciones por decisión en el período.',
  ));

  if (d.motivos_no_ingreso.length) {
    bloques.push(...tabla(
      'Paciente no llegó — motivos de no ingreso',
      ['Motivo', 'Cantidad', '%', ''],
      d.motivos_no_ingreso.map((m) => [esc(m.motivo), String(m.total), `${m.pct}%`, barra(m.pct, '#f97316')]),
      14,
    ));
  }

  const filasNegadas = (filas: FilaConteo[]) => filas.map((f) => [esc(f.nombre), String(f.total), `${f.pct}%`, barra(f.pct, '#ef4444')]);
  bloques.push(...tabla('Negadas por aseguradora', ['Aseguradora', 'Negadas', '% de las negadas', ''], filasNegadas(d.detalle_negadas.por_eps), 16, 'No hubo solicitudes negadas en el período.'));
  bloques.push(...tabla('Negadas por especialidad', ['Especialidad', 'Negadas', '% de las negadas', ''], filasNegadas(d.detalle_negadas.por_especialidad), 16, 'No hubo solicitudes negadas en el período.'));

  const colsDesglose = ['Nombre', 'Total', 'Aceptadas', 'Negadas', 'Cancel.', 'Pend.', '% aceptación'];
  const filasDesglose = (filas: FilaDesglose[]) => filas.map((f) => [esc(f.nombre), String(f.total), String(f.aceptadas), String(f.negadas), String(f.canceladas), String(f.pendientes), `${f.tasa_aceptacion}%`]);

  bloques.push(...tabla('Por EPS / aseguradora', colsDesglose, filasDesglose(d.por_eps), 16));
  bloques.push(...tabla('Por institución que refiere', colsDesglose, filasDesglose(d.por_clinica), 16));
  bloques.push(...tabla('Por especialidad', colsDesglose, filasDesglose(d.por_especialidad), 16));

  bloques.push(...tabla(
    'Casos aceptados',
    ['ID', 'Fecha', 'Paciente', 'EPS / Institución', 'Especialidad', 'Sede y servicio de llegada', 'Estado'],
    d.casos_aceptados.map((c) => [
      `#${c.id}`,
      esc(c.fecha),
      `${esc(c.paciente)}<br><span style="color:#94a3b8;">${esc(c.documento)}</span>`,
      `${esc(c.eps)}<br><span style="color:#94a3b8;">${esc(c.institucion ?? '—')}</span>`,
      esc(c.especialidad),
      `<strong>${esc(c.sede ?? 'Sin sede')}</strong><br>${esc(c.servicio_llegada ?? 'Sin servicio')}${c.codigo_aceptacion ? `<br><span style="color:#94a3b8;">Código ${esc(c.codigo_aceptacion)}</span>` : ''}`,
      esc(c.estado),
    ]),
    9,
    'No hubo solicitudes aceptadas en el período.',
  ));

  bloques.push(...tabla(
    'Casos negados',
    ['ID', 'Fecha', 'Paciente', 'EPS / Institución', 'Especialidad', 'Motivo y observaciones'],
    d.casos_negados.map((c) => [
      `#${c.id}`,
      esc(c.fecha),
      `${esc(c.paciente)}<br><span style="color:#94a3b8;">${esc(c.documento)}</span>`,
      `${esc(c.eps)}<br><span style="color:#94a3b8;">${esc(c.institucion ?? '—')}</span>`,
      esc(c.especialidad),
      `<strong>${esc(c.grupo_negacion ?? (c.motivo_no_ingreso ? 'No ingreso' : ''))}</strong> ${esc(c.motivo_no_ingreso ? c.motivo_no_ingreso : c.motivo_negacion)}${c.observaciones ? `<br><span style="color:#64748b;">${esc(c.observaciones)}</span>` : ''}`,
    ]),
    7,
    'No hubo solicitudes negadas en el período.',
  ));

  return bloques;
}

export async function exportarInformePdf(datos: InformeDatos): Promise<void> {
  const pdf = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
  const anchoPagina = pdf.internal.pageSize.getWidth();
  const altoPagina = pdf.internal.pageSize.getHeight();
  const margen = 20;
  const pie = 26;
  const anchoUtil = anchoPagina - margen * 2;

  let y = margen;

  for (const html of construirBloques(datos)) {
    const contenedor = document.createElement('div');
    contenedor.style.cssText = `position:fixed; left:-9999px; top:0; width:${ANCHO}px; background:#fff;`;
    contenedor.innerHTML = html;
    document.body.appendChild(contenedor);

    try {
      const canvas = await html2canvas(contenedor, { scale: 2, backgroundColor: '#ffffff', useCORS: true });
      const alto = (canvas.height * anchoUtil) / canvas.width;

      if (y + alto > altoPagina - pie && y > margen) {
        pdf.addPage();
        y = margen;
      }

      pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', margen, y, anchoUtil, alto);
      y += alto + 4;
    } finally {
      document.body.removeChild(contenedor);
    }
  }

  const paginas = pdf.getNumberOfPages();
  for (let p = 1; p <= paginas; p++) {
    pdf.setPage(p);
    pdf.setFontSize(8);
    pdf.setTextColor(148, 163, 184);
    pdf.text(`Informe de gestión de referencias · ${datos.periodo.desde} a ${datos.periodo.hasta}`, margen, altoPagina - 12);
    pdf.text(`Página ${p} de ${paginas}`, anchoPagina - margen, altoPagina - 12, { align: 'right' });
  }

  pdf.save(`informe-referencias_${datos.periodo.desde}_${datos.periodo.hasta}.pdf`);
}
