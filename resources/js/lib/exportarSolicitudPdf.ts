import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';

/**
 * Genera y descarga directamente el PDF de una solicitud — sin ventana
 * emergente ni diálogo de impresión (antes usaba window.open + document.write
 * + window.print(), lo que además ejecutaba el HTML interpolado como
 * documento real, un vector de XSS si algún campo del paciente traía
 * markup). Ahora se arma en un contenedor oculto de la misma página
 * (innerHTML no ejecuta <script>), con todo el texto dinámico escapado,
 * se rasteriza con html2canvas y se exporta con jsPDF.
 */

const ESTADOS: Record<string, { label: string; color: string }> = {
  pendiente: { label: 'Nueva solicitud', color: '#f59e0b' },
  pendiente_cama: { label: 'Pendiente', color: '#f97316' },
  en_espera: { label: 'Aceptado', color: '#3b82f6' },
  completado: { label: 'Completada', color: '#22c55e' },
  negado: { label: 'Negada', color: '#ef4444' },
  cancelado: { label: 'Cancelada', color: '#64748b' },
};

function esc(valor: unknown): string {
  return String(valor ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c] as string));
}

function formatFecha(fecha: string): string {
  if (!fecha) return '—';
  // La columna `fecha` llega serializada como "YYYY-MM-DDT00:00:00.000000Z" —
  // con hora en medianoche es una fecha de calendario, no un instante: se toma
  // la parte de fecha en hora local para no correrla un día hacia atrás.
  const esSoloFecha = !fecha.includes('T') || fecha.slice(11, 19) === '00:00:00';
  const d = new Date(esSoloFecha ? fecha.slice(0, 10) + 'T00:00:00' : fecha);
  if (isNaN(d.getTime())) return fecha;
  return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' });
}

export async function exportarSolicitudPdf(s: any): Promise<void> {
  if (!s) return;

  const estado = ESTADOS[s.estado] ?? { label: s.estado, color: '#64748b' };
  const paciente = esc(`${s.primer_nombre ?? ''} ${s.segundo_nombre ?? ''} ${s.primer_apellido ?? ''} ${s.segundo_apellido ?? ''}`.replace(/\s+/g, ' ').trim());

  const diagnosticosHtml = s.diagnosticos?.length
    ? s.diagnosticos.map((dx: any) => {
        const cod = esc(dx.codigo_cie10);
        const desc = esc(dx.descripcion);
        return `<p>${cod && !dx.descripcion?.startsWith(dx.codigo_cie10) ? `<strong>${cod}</strong> — ${desc}` : desc}</p>`;
      }).join('')
    : `<p>${esc(s.diagnostico) || '—'}</p>`;

  const contenedor = document.createElement('div');
  contenedor.style.cssText = 'position:fixed; left:-9999px; top:0; width:780px; background:#f8faff;';
  contenedor.innerHTML = `
    <div style="font-family:'Segoe UI',Arial,sans-serif; color:#1e293b; background:#f8faff; padding:2rem; width:780px; box-sizing:border-box;">
      <div style="background:linear-gradient(125deg,#0d2d6b,#16468e); border-radius:16px; padding:1.5rem; color:#fff; display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
        <div>
          <h1 style="font-size:1.1rem; font-weight:800; margin:0;">Solicitud de Referencia #${Number(s.id)}</h1>
          <p style="font-size:.75rem; opacity:.7; margin-top:.2rem;">${esc(formatFecha(s.created_at))}</p>
        </div>
        <div style="margin-left:auto; padding:.35rem .8rem; border-radius:999px; font-size:.7rem; font-weight:700; background:${estado.color}; color:#fff;">${esc(estado.label)}</div>
      </div>

      ${s.codigo_aceptacion ? `<div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5); border:1px solid #a7f3d0; border-radius:10px; padding:.8rem 1rem; display:flex; align-items:center; gap:.6rem; margin-bottom:1rem;"><span style="font-size:.75rem; color:#166534; font-weight:600;">Código de aceptación</span><strong style="margin-left:auto; font-family:monospace; font-size:1rem; font-weight:800; color:#166534; background:#fff; padding:.2rem .6rem; border-radius:6px; border:1px solid #86efac;">${esc(s.codigo_aceptacion)}</strong></div>` : ''}

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem;">
          <h2 style="font-size:.75rem; font-weight:800; color:#0d2d5e; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .6rem; padding-bottom:.4rem; border-bottom:2px solid #f1f5f9;">Paciente</h2>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Nombre</span><strong>${paciente}</strong></div>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Documento</span><strong>${esc(s.tipo_documento)} ${esc(s.numero_documento)}</strong></div>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Edad / Género</span><strong>${Number(s.edad) || 0} años · ${s.genero === 'M' ? 'Masculino' : 'Femenino'}</strong></div>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">EPS</span><strong>${esc(s.eps)}</strong></div>
        </div>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem;">
          <h2 style="font-size:.75rem; font-weight:800; color:#0d2d5e; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .6rem; padding-bottom:.4rem; border-bottom:2px solid #f1f5f9;">Remisión</h2>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Institución</span><strong>${esc(s.clinica?.nombre) || '—'}</strong></div>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Especialidad</span><strong>${esc(s.especialidad_requerida)}</strong></div>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Tipo de referencia</span><strong>${esc(s.tipo_referencia) || '—'}</strong></div>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Servicio actual</span><strong>${esc(s.servicio_ubicacion_actual)}</strong></div>
          <div style="display:flex; justify-content:space-between; padding:.25rem 0; font-size:.78rem;"><span style="color:#94a3b8;">Municipio</span><strong>${esc(s.municipio_capita)}</strong></div>
        </div>
      </div>

      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; margin-bottom:1rem;">
        <h2 style="font-size:.75rem; font-weight:800; color:#0d2d5e; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; padding-bottom:.4rem; border-bottom:2px solid #f1f5f9;">Diagnósticos</h2>
        <div style="font-size:.78rem; line-height:1.6; color:#475569;">${diagnosticosHtml}</div>
      </div>

      <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; margin-bottom:1rem;">
        <h2 style="font-size:.75rem; font-weight:800; color:#0d2d5e; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; padding-bottom:.4rem; border-bottom:2px solid #f1f5f9;">Historia clínica</h2>
        <p style="font-size:.78rem; line-height:1.6; color:#475569; white-space:pre-wrap;">${esc(s.resumen_historia_clinica)}</p>
      </div>

      ${s.nombre_quien_responde ? `<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; margin-bottom:1rem;"><h2 style="font-size:.75rem; font-weight:800; color:#0d2d5e; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; padding-bottom:.4rem; border-bottom:2px solid #f1f5f9;">Respuesta del equipo</h2><p style="font-size:.78rem; line-height:1.6; color:#475569;"><strong>Respondió:</strong> ${esc(s.nombre_quien_responde)} · ${esc(s.hora_respuesta)}</p>${s.observaciones_respuesta ? `<p style="font-size:.78rem; line-height:1.6; color:#475569; margin-top:.5rem;">${esc(s.observaciones_respuesta)}</p>` : ''}</div>` : ''}

      ${s.motivo_negacion ? `<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; margin-bottom:1rem;"><h2 style="font-size:.75rem; font-weight:800; color:#0d2d5e; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; padding-bottom:.4rem; border-bottom:2px solid #f1f5f9;">Motivo de negación</h2><p style="font-size:.78rem; line-height:1.6; color:#475569;">${s.grupo_negacion ? `<strong>${esc(s.grupo_negacion === 'no_red' ? 'Paciente no RED' : 'Población objeto')}</strong> — ` : ''}${esc(s.motivo_negacion)}</p></div>` : ''}

      <div style="margin-top:2rem; padding-top:1rem; border-top:1px solid #e2e8f0; text-align:center;">
        <p style="font-size:.8rem; font-weight:800; color:#0d2d6b; margin-bottom:.3rem;">Clínica CAC Santa Bárbara</p>
        <p style="font-size:.65rem; color:#94a3b8;">Documento generado el ${esc(formatFecha(new Date().toISOString()))}</p>
      </div>
    </div>
  `;

  document.body.appendChild(contenedor);
  try {
    const canvas = await html2canvas(contenedor, { scale: 2, backgroundColor: '#f8faff', useCORS: true });
    const imgData = canvas.toDataURL('image/jpeg', 0.95);

    const pdf = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const imgHeight = (canvas.height * pageWidth) / canvas.width;

    let heightLeft = imgHeight;
    let position = 0;
    pdf.addImage(imgData, 'JPEG', 0, position, pageWidth, imgHeight);
    heightLeft -= pageHeight;

    while (heightLeft > 0) {
      position = heightLeft - imgHeight;
      pdf.addPage();
      pdf.addImage(imgData, 'JPEG', 0, position, pageWidth, imgHeight);
      heightLeft -= pageHeight;
    }

    pdf.save(`solicitud-${s.id}.pdf`);
  } finally {
    document.body.removeChild(contenedor);
  }
}
