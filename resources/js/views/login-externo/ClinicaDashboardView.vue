<template>
  <div class="ph-dashboard h-full flex overflow-hidden">

    <!-- ══ Columna principal ═══════════════════════════════════════════════ -->
    <div class="flex-1 flex flex-col gap-2 p-2.5 sm:p-3 min-w-0 overflow-y-auto lg:overflow-hidden overflow-x-hidden custom-scrollbar">

      <!-- ── Hero de bienvenida ── -->
      <div class="landing-hero lg:flex-1 lg:min-h-0 animate-fade-in-up"
        style="animation-duration: 0.4s; animation-delay: 0.1s; animation-fill-mode: both;">
        <div class="landing-photo" aria-hidden="true"></div>

        <div class="landing-inner">
          <div class="landing-brand">
            <div class="landing-logo-circle">
              <img :src="logoAvatar" alt="Clínica Santa Bárbara" />
            </div>
            <div class="landing-brand-text">
              <strong>Santa Bárbara</strong>
              <span>Sistema de referencia médica</span>
            </div>
          </div>

          <div class="landing-main">
            <h1 class="landing-title">Aquí puedes referir a tus<br><span>pacientes de forma rápida,</span><br>segura y confiable</h1>
            <p class="landing-desc">Registra y envía las solicitudes de referencia médica a la Clínica Santa Bárbara con seguimiento en tiempo real.</p>
            <button class="landing-cta" @click="abrirFormulario()">
              <component :is="FilePlusIcon" class="w-[18px] h-[18px]" />
              Nueva solicitud de referencia
              <component :is="ArrowRightIcon" class="w-[18px] h-[18px] landing-cta-arrow" />
            </button>
          </div>
        </div>
      </div>

    </div>


    <!-- ── Modales ──────────────────────────────────────────────────────── -->

    <!-- ── Modal: Éxito envío ─────────────────────────────────────────── -->
    <el-dialog v-model="modalExito" width="480px" class="exito-dialog" :show-close="false" align-center @close="onCerrarExito">
      <div class="exito-content">
        <div class="exito-icon-circle">
          <component :is="CheckCircleIcon" class="w-10 h-10" />
        </div>
        <h2 class="exito-title">¡Solicitud enviada!</h2>
        <p class="exito-subtitle">Su solicitud de referencia ha sido registrada correctamente</p>
        <div class="exito-details">
          <div class="exito-detail-row"><span>Paciente</span><strong>{{ ultimoEnviado.paciente }}</strong></div>
          <div class="exito-detail-row"><span>Documento</span><strong>{{ ultimoEnviado.documento }}</strong></div>
          <div class="exito-detail-row"><span>Especialidad</span><strong>{{ ultimoEnviado.especialidad }}</strong></div>
          <div class="exito-detail-row"><span>Diagnósticos</span><strong style="white-space: pre-line;">{{ ultimoEnviado.diagnosticos }}</strong></div>
          <div class="exito-detail-row"><span>Servicio actual</span><strong>{{ ultimoEnviado.servicio }}</strong></div>
          <div class="exito-detail-row" v-if="ultimoEnviado.adjuntos"><span>Adjuntos</span><strong>{{ ultimoEnviado.adjuntos }}</strong></div>
        </div>
        <el-button type="primary" class="exito-btn !bg-[#0D2D6B] w-full" @click="modalExito = false">Entendido</el-button>
      </div>
    </el-dialog>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { usePolling } from '@/lib/usePolling';
import { actualizarSiCambio } from '@/lib/silentRefresh';
import { useRouter } from 'vue-router';
import { ElMessageBox } from 'element-plus';
import notify from '@/plugins/toast';
import {
  X as XIcon,
  ClipboardList as ClipboardListIcon,
  Clock as ClockIcon,
  Hourglass as HourglassIcon,
  CheckCircle as CheckCircleIcon,
  XCircle as XCircleIcon,
  Stethoscope as StethoscopeIcon,
  RefreshCw as RefreshCwIcon,
  ArrowRight as ArrowRightIcon,
  FilePlus as FilePlusIcon,
  Paperclip as PaperclipIcon,
  UploadCloud as UploadCloudIcon,
  CheckCircle2 as CheckCircle2Icon,
  User as UserIcon,
  UserCheck as UserCheckIcon,
  FileCheck as FileCheckIcon,
  TrendingUp as TrendingUpIcon,
  Activity as ActivityIcon,
} from '@lucide/vue';
import VueApexCharts from 'vue3-apexcharts';

import http from '@/plugins/axios';
import Card from '@/components/ui/card/Card.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import StatCard, { type StatCardTone } from '@/components/ui/StatCard.vue';
import { useClinicaLayoutStore } from '@/stores/clinicaLayout';
import { TIPOS_DOCUMENTO, EPS_LIST, ESPECIALIDADES, SERVICIOS } from '@/data/referencia';

const apexchart = VueApexCharts;

const logoAvatar = '/images/logo-avatar.png';
const layout = useClinicaLayoutStore();
const isDark = computed(() => layout.isDarkMode);
const router = useRouter();
const solicitudes = ref<any[]>([]);
const cargando = ref(false);
const chartsReady = ref(false);
const drawerVisible = ref(false);
const guardando = ref(false);
const showConfirmResumen = ref(false);
const formRef = ref();
const adjuntos = ref<File[]>([]);
const dragOver = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const pasoFormulario = ref(1);

interface DiagnosticoItem {
  codigo_cie10: string;
  descripcion: string;
}

const diagnosticos = ref<DiagnosticoItem[]>([{ codigo_cie10: '', descripcion: '' }]);

function agregarDiagnostico() {
  diagnosticos.value.push({ codigo_cie10: '', descripcion: '' });
}

function quitarDiagnostico(i: number) {
  diagnosticos.value.splice(i, 1);
}

async function onEspecialidadSelect(val: string) {
  if (val === '__otra_esp') {
    form.value.especialidad_requerida = '';
    try {
      const { value } = await ElMessageBox.prompt('Escriba el nombre de la especialidad', 'Nueva especialidad', {
        confirmButtonText: 'Agregar',
        cancelButtonText: 'Cancelar',
        inputPlaceholder: 'Ej: Cardiología pediátrica',
        inputValidator: (v) => v?.trim() ? true : 'El nombre es requerido',
      });
      form.value.especialidad_requerida = value.trim();
    } catch {
      form.value.especialidad_requerida = '';
    }
    return;
  }
}
const formSteps = [
  { number: 1, label: 'Paciente' },
  { number: 2, label: 'Remisión' },
];

// ── Stat cards config ───────────────────────────────────────────────────────
const statCards = computed((): { label: string; icon: typeof ClipboardListIcon; tone: StatCardTone; delta: string; percent: number; sparkline: number[]; comparacion: string }[] => [
  {
    label: 'Solicitudes totales',
    icon: ClipboardListIcon,
    tone: 'info',
    delta: stats.value.pendientes ? `${stats.value.pendientes} pend.` : 'sin pendientes',
    percent: 100,
    sparkline: conteoPorDia(14, 0),
    comparacion: comparacionPeriodo(),
  },
  {
    label: 'Pendientes',
    icon: ClockIcon,
    tone: 'warning',
    delta: stats.value.pendientes ? 'Sin revisar' : 'Al día',
    percent: stats.value.total ? Math.round((stats.value.pendientes / stats.value.total) * 100) : 0,
    sparkline: conteoPorDia(14, 0, s => s.estado === 'pendiente'),
    comparacion: comparacionPeriodo(s => s.estado === 'pendiente'),
  },
  {
    label: 'En espera',
    icon: HourglassIcon,
    tone: 'info',
    delta: stats.value.enEspera ? 'Esperando llegada' : 'Sin pacientes en espera',
    percent: stats.value.total ? Math.round((stats.value.enEspera / stats.value.total) * 100) : 0,
    sparkline: conteoPorDia(14, 0, s => s.estado === 'en_espera'),
    comparacion: comparacionPeriodo(s => s.estado === 'en_espera'),
  },
  {
    label: 'Completadas',
    icon: CheckCircleIcon,
    tone: 'violet',
    delta: `${tasaAceptacion.value}% tasa`,
    percent: stats.value.total ? Math.round((stats.value.completadas / stats.value.total) * 100) : 0,
    sparkline: conteoPorDia(14, 0, s => s.estado === 'completado'),
    comparacion: comparacionPeriodo(s => s.estado === 'completado'),
  },
  {
    label: 'Negadas',
    icon: XCircleIcon,
    tone: 'danger',
    delta: stats.value.negadas ? 'Revisar' : 'Sin rechazos',
    percent: stats.value.total ? Math.round((stats.value.negadas / stats.value.total) * 100) : 0,
    sparkline: conteoPorDia(14, 0, s => s.estado === 'negado'),
    comparacion: comparacionPeriodo(s => s.estado === 'negado'),
  },
]);

// ── Tendencia chart (multi-línea: Total/Completadas/Pendientes/Negadas) ─────
const tendenciaSeries = computed(() => {
  const dias = 14;
  return [
    { name: 'Total', data: conteoPorDia(dias, 0) },
    { name: 'Completadas', data: conteoPorDia(dias, 0, s => s.estado === 'completado') },
    { name: 'Pendientes', data: conteoPorDia(dias, 0, s => s.estado === 'pendiente' || s.estado === 'en_espera') },
    { name: 'Negadas', data: conteoPorDia(dias, 0, s => s.estado === 'negado') },
  ];
});

/**
 * Conteo diario de los últimos `dias`, con un desfase de `offsetDias` hacia
 * atrás (offset 0 = ventana actual, offset 14 = los 14 días previos a esos).
 * Mismo agrupado por `created_at` que ya usa `tendenciaSeries`, reutilizado
 * para los mini-gráficos de cada stat-card y la comparación de periodos.
 */
function conteoPorDia(dias: number, offsetDias: number, filtro?: (s: any) => boolean): number[] {
  const base = filtro ? solicitudes.value.filter(filtro) : solicitudes.value;
  const conteo: Record<string, number> = {};
  base.forEach(s => {
    const fecha = new Date(s.created_at).toISOString().slice(0, 10);
    conteo[fecha] = (conteo[fecha] ?? 0) + 1;
  });
  const datos: number[] = [];
  for (let i = dias - 1; i >= 0; i--) {
    const fecha = new Date(Date.now() - (i + offsetDias) * 86400000).toISOString().slice(0, 10);
    datos.push(conteo[fecha] ?? 0);
  }
  return datos;
}

/** "↑12% vs periodo anterior": compara los últimos 14 días contra los 14 previos a esos (no hay volumen para comparar contra un año fijo). */
function comparacionPeriodo(filtro?: (s: any) => boolean): string {
  const actual = conteoPorDia(14, 0, filtro).reduce((a, b) => a + b, 0);
  const anterior = conteoPorDia(14, 14, filtro).reduce((a, b) => a + b, 0);
  if (anterior === 0) return actual > 0 ? `+${actual} vs periodo anterior` : 'Sin cambios';
  const pct = Math.round(((actual - anterior) / anterior) * 100);
  return `${pct >= 0 ? '↑' : '↓'}${Math.abs(pct)}% vs periodo anterior`;
}

const tendenciaTotal = computed(() => tendenciaSeries.value[0].data.reduce((a: number, b: number) => a + b, 0));

const TENDENCIA_COLORES = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444'];

const tendenciaOptions = computed(() => ({
  chart: {
    type: 'line' as const,
    fontFamily: 'inherit',
    background: 'transparent',
    toolbar: { show: false },
    sparkline: { enabled: false },
    animations: {
      enabled: true,
      easing: 'easeinout' as const,
      speed: 800,
      animateGradually: { enabled: true, delay: 150 },
      dynamicAnimation: { enabled: true, speed: 350 },
    },
  },
  colors: TENDENCIA_COLORES,
  stroke: { curve: 'smooth' as const, width: [3, 2, 2, 2], lineCap: 'round' as const },
  dataLabels: { enabled: false },
  legend: {
    show: true,
    position: 'top' as const,
    horizontalAlign: 'right' as const,
    fontSize: '10px',
    fontWeight: 600,
    labels: { colors: isDark.value ? '#94a3b8' : '#64748b' },
    markers: { size: 4, offsetX: -2 },
    itemMargin: { horizontal: 8, vertical: 0 },
  },
  grid: {
    borderColor: isDark.value ? '#1e293b' : '#f1f5f9',
    strokeDashArray: 5,
    padding: { top: 5, right: 10, bottom: 5, left: 10 },
    yaxis: { lines: { show: true } },
    xaxis: { lines: { show: false } },
  },
  xaxis: {
    categories: Array.from({ length: 14 }, (_, i) => {
      const d = new Date(Date.now() - (13 - i) * 86400000);
      return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short' });
    }),
    labels: { style: { fontSize: '10px', colors: isDark.value ? '#64748b' : '#94a3b8' }, rotate: 0 },
    axisBorder: { show: false },
    axisTicks: { show: false },
    crosshairs: {
      stroke: { color: isDark.value ? '#334155' : '#cbd5e1', dashArray: 3 },
      fill: { type: 'solid', color: isDark.value ? '#1e293b' : '#f1f5f9' },
    },
  },
  yaxis: {
    show: true,
    labels: {
      style: { fontSize: '9px', colors: isDark.value ? '#475569' : '#cbd5e1' },
      formatter: (val: number) => val === 0 ? '' : String(Math.round(val)),
    },
    tickAmount: 3,
  },
  tooltip: {
    shared: true,
    y: { formatter: (val: number) => `${val} solicitud${val !== 1 ? 'es' : ''}` },
    style: { fontSize: '12px', fontFamily: 'inherit' },
    theme: isDark.value ? 'dark' as const : 'light' as const,
  },
  markers: {
    // Puntos siempre visibles (no solo al pasar el mouse): con pocas
    // solicitudes reales, una línea casi plana sin marcadores se lee como
    // "vacía" aunque sí haya datos puntuales.
    size: 2.5,
    hover: { size: 5, sizeOffset: 2 },
    strokeColors: isDark.value ? '#0f172a' : '#fff',
    strokeWidth: 2,
  },
  annotations: {
    points: tendenciaTotal.value > 0 ? [{
      x: new Date().toLocaleDateString('es-CO', { day: '2-digit', month: 'short' }),
      seriesIndex: 0,
      label: {
        text: 'Hoy',
        style: {
          color: '#fff',
          background: TENDENCIA_COLORES[0],
          fontSize: '9px',
          fontWeight: 700,
          padding: { left: 5, right: 5, top: 2, bottom: 2 },
        },
      },
    }] : [],
  },
}));

// ── Distribución de estados (barras horizontales) ───────────────────────────
const distribucionBarras = computed(() => {
  const total = Math.max(1, stats.value.total);
  return [
    { label: 'Pendientes', count: stats.value.pendientes, pct: Math.round((stats.value.pendientes / total) * 100), color: '#f59e0b', gradient: 'linear-gradient(90deg, #fbbf24, #f59e0b)' },
    { label: 'En espera', count: stats.value.enEspera, pct: Math.round((stats.value.enEspera / total) * 100), color: '#3b82f6', gradient: 'linear-gradient(90deg, #60a5fa, #3b82f6)' },
    { label: 'Completadas', count: stats.value.completadas, pct: Math.round((stats.value.completadas / total) * 100), color: '#22c55e', gradient: 'linear-gradient(90deg, #4ade80, #22c55e)' },
    { label: 'Negadas', count: stats.value.negadas, pct: Math.round((stats.value.negadas / total) * 100), color: '#ef4444', gradient: 'linear-gradient(90deg, #f87171, #ef4444)' },
  ];
});

// ── Donut de distribución de estados (misma familia que el dashboard interno) ─
const estadoDonutSeries = computed(() => distribucionBarras.value.map(b => b.count));

const estadoDonutOptions = computed(() => ({
  chart: { type: 'donut' as const, fontFamily: 'inherit', sparkline: { enabled: true }, animations: { enabled: true, speed: 700 } },
  labels: distribucionBarras.value.map(b => b.label),
  colors: distribucionBarras.value.map(b => b.color),
  stroke: { width: 2, colors: [isDark.value ? '#151a24' : '#ffffff'] },
  legend: { show: false },
  dataLabels: { enabled: false },
  tooltip: {
    theme: isDark.value ? 'dark' as const : 'light' as const,
    style: { fontFamily: 'inherit' },
    y: { formatter: (val: number) => `${val} solicitud${val !== 1 ? 'es' : ''}` },
  },
  plotOptions: { pie: { donut: { size: '72%', labels: { show: false } } } },
}));

// ── Tasa de rechazo ─────────────────────────────────────────────────────────
const tasaRechazo = computed(() => {
  if (!stats.value.total) return 0;
  return Math.round((stats.value.negadas / stats.value.total) * 100);
});

// Contador animado
const displayStats = ref([0, 0, 0, 0, 0]);

function animateCounters(targets: number[]) {
  const desde = [...displayStats.value];
  if (desde.every((d, i) => d === targets[i])) return;
  const inicio = performance.now();
  const dur = 700;
  const tick = (t: number) => {
    const k = Math.min(1, (t - inicio) / dur);
    const e = 1 - Math.pow(1 - k, 3);
    displayStats.value = targets.map((o, i) => Math.round(desde[i] + (o - desde[i]) * e));
    if (k < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

const stats = computed(() => ({
  total: solicitudes.value.length,
  pendientes: solicitudes.value.filter(s => s.estado === 'pendiente').length,
  enEspera: solicitudes.value.filter(s => s.estado === 'en_espera').length,
  completadas: solicitudes.value.filter(s => s.estado === 'completado').length,
  negadas: solicitudes.value.filter(s => s.estado === 'negado').length,
}));

const statPercents = computed(() => {
  const total = Math.max(1, stats.value.total);
  return [
    100,
    Math.round((stats.value.pendientes / total) * 100),
    Math.round((stats.value.negadas / total) * 100),
  ];
});

// "Aceptadas" ya no es un estado propio (aceptar pasa directo a en_espera),
// así que la tasa de aceptación cuenta todo lo que avanzó más allá de
// pendiente sin ser negado.
const tasaAceptacion = computed(() => {
  if (!stats.value.total) return 0;
  return Math.round(((stats.value.enEspera + stats.value.completadas) / stats.value.total) * 100);
});

// ── Sparklines decorativas por fila ─────────────────────────────────────────
const SPARKLINE_PATHS = [
  'M2 14 C 10 6, 18 20, 26 12 S 42 4, 50 12 S 58 18, 62 10',
  'M2 12 C 10 18, 18 6, 26 14 S 42 20, 50 8 S 58 12, 62 14',
  'M2 10 C 10 14, 18 8, 26 16 S 42 6, 50 14 S 58 8, 62 12',
];

function sparklinePath(idx: number): string {
  return SPARKLINE_PATHS[idx % SPARKLINE_PATHS.length];
}

function emptyForm() {
  return {
    primer_nombre: '', segundo_nombre: '', primer_apellido: '', segundo_apellido: '',
    genero: '', edad: null as number | null, tipo_documento: '', numero_documento: '',
    eps: '', municipio_capita: '', especialidad_requerida: '',
    servicio_ubicacion_actual: '', servicio_remision: '', quien_remitente: '', telefono_contacto: '', correo_contacto: '', via_contacto: '',
    gestante: null as boolean | null, condicion_especial: '',
    resumen_historia_clinica: '', observaciones: '',
  };
}

const form = ref(emptyForm());

function capitalizar(campo: 'primer_nombre' | 'segundo_nombre' | 'primer_apellido' | 'segundo_apellido' | 'municipio_capita' | 'resumen_historia_clinica' | 'condicion_especial' | 'quien_remitente' | 'observaciones') {
  const val = form.value[campo];
  if (val && val.length === 1) {
    form.value[campo] = val.charAt(0).toUpperCase();
  } else if (val && val.length > 1) {
    form.value[campo] = val.charAt(0).toUpperCase() + val.slice(1);
  }
}

const nombreCompleto = computed(() =>
  [form.value.primer_nombre, form.value.segundo_nombre, form.value.primer_apellido, form.value.segundo_apellido]
    .filter(Boolean)
    .join(' ') || '—'
);

const rules = {
  primer_nombre: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  primer_apellido: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  genero: [{ required: true, message: 'Requerido', trigger: 'change' }],
  edad: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  tipo_documento: [{ required: true, message: 'Requerido', trigger: 'change' }],
  numero_documento: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  eps: [{ required: true, message: 'Requerido', trigger: 'change' }],
  municipio_capita: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  especialidad_requerida: [{ required: true, message: 'Requerido', trigger: 'change' }],
  servicio_ubicacion_actual: [{ required: true, message: 'Requerido', trigger: 'change' }],
  resumen_historia_clinica: [{ required: true, message: 'Requerido', trigger: 'blur' }],
  correo_contacto: [{ type: 'email', message: 'Correo no válido', trigger: 'blur' }],
};

async function cargar() {
  cargando.value = true;
  try {
    const { data } = await http.get('/api/externo/solicitudes');
    solicitudes.value = data.data;
  } catch { notify.error('Error al cargar solicitudes'); }
  finally {
    cargando.value = false;
    await nextTick();
    await nextTick();
    chartsReady.value = true;
  }
}

/** Refresco automático de fondo: sin esqueleto de carga, y solo toca lo que
 * de verdad cambió, resaltando esa fila puntual en "Últimas referencias". */
async function cargarSilencioso() {
  try {
    const { data } = await http.get('/api/externo/solicitudes');
    actualizarSiCambio(solicitudes, data.data);
  } catch {
    // Refresco de fondo: si falla, se reintenta en el siguiente ciclo sin interrumpir al usuario.
  }
}

function abrirFormulario() { router.push('/clinica/solicitud'); }

function formularioTieneDatos(): boolean {
  const f = form.value;
  return !!(f.primer_nombre || f.primer_apellido || f.numero_documento ||
    diagnosticos.value.some(d => d.codigo_cie10 || d.descripcion) ||
    f.especialidad_requerida || f.resumen_historia_clinica || adjuntos.value.length > 0);
}

async function confirmarCierre(done: () => void) {
  if (!formularioTieneDatos()) { done(); return; }
  try {
    await ElMessageBox.confirm(
      'Tiene datos sin guardar. ¿Desea cerrar el formulario?',
      'Confirmar cierre',
      { confirmButtonText: 'Cerrar', cancelButtonText: 'Seguir editando', type: 'warning' },
    );
    done();
  } catch { /* cancelado */ }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'n' || e.key === 'N') {
    const tag = (e.target as HTMLElement)?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (e.target as HTMLElement)?.isContentEditable) return;
    e.preventDefault();
    abrirFormulario();
  }
}
function seleccionarAdjuntos(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files ?? []);
  agregarAdjuntos(files);
  if (fileInput.value) fileInput.value.value = '';
}

function onDrop(event: DragEvent) {
  dragOver.value = false;
  const files = Array.from(event.dataTransfer?.files ?? []);
  agregarAdjuntos(files);
}

function agregarAdjuntos(files: File[]) {
  const restantes = 10 - adjuntos.value.length;
  if (restantes <= 0) {
    notify.warning('Máximo 10 archivos permitidos');
    return;
  }
  const nuevos = files.slice(0, restantes);
  if (files.length > restantes) {
    notify.warning(`Solo se agregaron ${restantes} de ${files.length} archivos (límite 10)`);
  }
  adjuntos.value = [...adjuntos.value, ...nuevos];
}

function removerAdjunto(index: number) {
  adjuntos.value.splice(index, 1);
}

function getFileTypeLabel(file: File): string {
  const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
  if (['pdf'].includes(ext)) return 'PDF';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'].includes(ext)) return 'IMG';
  if (['doc', 'docx'].includes(ext)) return 'DOC';
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'XLS';
  if (['ppt', 'pptx'].includes(ext)) return 'PPT';
  if (['zip', 'rar'].includes(ext)) return 'ZIP';
  if (['txt'].includes(ext)) return 'TXT';
  return ext.toUpperCase().slice(0, 3);
}

function getFileTypeClass(file: File): string {
  const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
  if (['pdf'].includes(ext)) return 'ft-pdf';
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'].includes(ext)) return 'ft-img';
  if (['doc', 'docx'].includes(ext)) return 'ft-doc';
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'ft-xls';
  if (['ppt', 'pptx'].includes(ext)) return 'ft-ppt';
  if (['zip', 'rar'].includes(ext)) return 'ft-zip';
  return 'ft-default';
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function avanzarPaso(): Promise<void> {
  const camposPorPaso = [
    ['primer_nombre', 'primer_apellido', 'genero', 'edad', 'tipo_documento', 'numero_documento', 'municipio_capita', 'eps'],
    ['resumen_historia_clinica', 'especialidad_requerida', 'servicio_ubicacion_actual'],
  ];

  if (pasoFormulario.value === 2) {
    const validDx = diagnosticos.value.filter(d => d.codigo_cie10.trim() && d.descripcion.trim());
    if (validDx.length === 0) {
      notify.warning('Agregue al menos un diagnóstico con código y descripción');
      return;
    }
  }

  try {
    await formRef.value?.validateField(camposPorPaso[pasoFormulario.value - 1]);
    pasoFormulario.value++;
  } catch {
    notify.warning('Complete los campos requeridos para continuar');
  }
}

async function abrirConfirmacion(): Promise<void> {
  const validDx = diagnosticos.value.filter(d => d.codigo_cie10.trim() && d.descripcion.trim());
  if (validDx.length === 0) {
    notify.warning('Agregue al menos un diagnóstico con código y descripción');
    return;
  }
  try {
    await formRef.value?.validate();
    showConfirmResumen.value = true;
  } catch {
    notify.error('Por favor complete todos los campos requeridos');
  }
}

const modalExito = ref(false);
const ultimoEnviado = ref({
  paciente: '', documento: '', especialidad: '', diagnosticos: '', servicio: '', adjuntos: '',
});

function onCerrarExito() {
  ultimoEnviado.value = { paciente: '', documento: '', especialidad: '', diagnosticos: '', servicio: '', adjuntos: '' };
}

async function guardar() {
  try { await formRef.value?.validate(); }
  catch { notify.error('Por favor complete todos los campos requeridos'); return; }
  guardando.value = true;
  try {
    const payload = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
      if (value !== null && value !== '') {
        payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
      }
    });
    const validDx = diagnosticos.value.filter(d => d.codigo_cie10.trim() && d.descripcion.trim());
    validDx.forEach((dx, i) => {
      payload.append(`diagnosticos[${i}][codigo_cie10]`, dx.codigo_cie10.trim());
      payload.append(`diagnosticos[${i}][descripcion]`, dx.descripcion.trim());
    });
    adjuntos.value.forEach((archivo) => payload.append('adjuntos[]', archivo));
    await http.post('/api/externo/solicitudes', payload);
    ultimoEnviado.value = {
      paciente: nombreCompleto.value,
      documento: `${form.value.tipo_documento} ${form.value.numero_documento}`,
      especialidad: form.value.especialidad_requerida || '—',
      diagnosticos: validDx.map(d => (d.codigo_cie10 && !d.descripcion.startsWith(d.codigo_cie10)) ? `${d.codigo_cie10} — ${d.descripcion}` : d.descripcion).join('\n') || '—',
      servicio: form.value.servicio_ubicacion_actual || '—',
      adjuntos: adjuntos.value.length ? `${adjuntos.value.length} archivo(s)` : '',
    };
    drawerVisible.value = false;
    modalExito.value = true;
    await cargar();
  } catch (e: any) {
    const errors = e.response?.data?.data?.errors || e.response?.data?.errors;
    notify.error(errors ? Object.values(errors).flat().join('\n') : 'Error al enviar la solicitud');
  } finally { guardando.value = false; }
}

watch(stats, (s) => {
  animateCounters([s.total, s.pendientes, s.enEspera, s.completadas, s.negadas]);
});

watch(drawerVisible, (isOpen) => {
  if (isOpen) {
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    document.body.style.height = '100vh';
    document.querySelectorAll('main').forEach(el => { (el as HTMLElement).style.overflow = 'hidden'; });
  } else {
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    document.body.style.height = '';
    document.querySelectorAll('main').forEach(el => { (el as HTMLElement).style.overflow = ''; });
  }
});

onMounted(() => {
  // El portal es público: solo se consulta la bandeja de solicitudes si hay
  // una sesión de clínica válida (acceso antiguo con login).
  if (sessionStorage.getItem('clinica_token')) cargar();
  window.addEventListener('keydown', onKeydown);
});
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
});

usePolling(() => {
  if (!sessionStorage.getItem('clinica_token')) return;
  if (!cargando.value && !drawerVisible.value) cargarSilencioso();
}, 30000);
</script>

<style scoped>
/* El estilo del scroll es global en tailwind.css (invisible hasta hover, tipo Element Plus) */

/* ════════════════════════════════════════════════════════════════
   NUEVO FORMULARIO PROFESIONAL — Sistema de Referencia
   Paleta: #0D2D6B (azul institucional), #1E293B (slate), #F8FAFC (fondo)
   ════════════════════════════════════════════════════════════════ */

/* ── Dialog base ── */
:deep(.request-dialog) {
  max-width: calc(100vw - 2rem);
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 24px 64px rgba(13, 45, 107, .3);
}
:deep(.request-dialog .el-dialog__header) {
  margin: 0;
  padding: .6rem 1.25rem;
  border-bottom: 2px solid #0D2D6B;
  background: linear-gradient(135deg, #0D2D6B 0%, #16468E 100%);
  border-radius: 16px 16px 0 0;
}
:deep(.request-dialog .el-dialog__title) {
  color: #fff;
  font-size: .95rem;
  font-weight: 700;
}
:deep(.request-dialog .el-dialog__headerbtn) {
  position: fixed;
  top: calc(50vh - 50vh + 12px);
  right: calc(50vw - 550px + 12px);
  z-index: 9999;
  width: 30px;
  height: 30px;
  border: 1.5px solid #DC2626;
  border-radius: 8px;
  background: #fff;
  display: grid;
  place-items: center;
  transition: all .2s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
}
:deep(.request-dialog .el-dialog__headerbtn .el-dialog__close) {
  color: #DC2626;
  font-size: .9rem;
  font-weight: 700;
}
:deep(.request-dialog .el-dialog__headerbtn:hover) {
  background: #DC2626;
  border-color: #DC2626;
  box-shadow: 0 4px 14px rgba(220, 38, 38, .35);
}
:deep(.request-dialog .el-dialog__headerbtn:hover .el-dialog__close) { color: #fff; }
:global(.request-dialog .el-dialog__body) {
  max-height: calc(100vh - 3.5rem);
  padding: .5rem 1rem .75rem;
  overflow: hidden;
  background: #F8FAFC;
  display: flex;
  flex-direction: column;
}
:global(.request-dialog .el-dialog) {
  max-height: calc(100vh - .5rem);
  overflow: hidden;
  margin-top: .25rem !important;
  margin-bottom: .25rem !important;
}
:global(html:has(.request-dialog)),
:global(body:has(.request-dialog)) {
  height: 100%;
  overflow: hidden !important;
}
:global(.el-overlay) {
  background-color: rgba(15, 23, 42, .5);
  backdrop-filter: blur(3px);
  overflow: hidden !important;
}

/* ── Form overrides ── */
:deep(.rf-form-col .el-form) { flex: 1; display: flex; flex-direction: column; min-height: 0; }
:deep(.rf-form-col .el-form-item) { margin-bottom: 0; display: flex; flex-direction: column; }
:deep(.rf-form-col .el-form-item__label) {
  color: #475569; font-size: .68rem; font-weight: 600;
  padding-bottom: .1rem; line-height: 1.1;
}
:deep(.rf-form-col .el-form-item__error) { padding-top: 2px; font-size: .62rem; }
:deep(.rf-form-col .el-input__wrapper),
:deep(.rf-form-col .el-select__wrapper),
:deep(.rf-form-col .el-textarea__inner) {
  box-shadow: 0 0 0 1px #CBD5E1 inset;
  border-radius: 8px;
  background: #fff;
  transition: box-shadow .2s ease;
}
:deep(.rf-form-col .el-input__wrapper.is-focus),
:deep(.rf-form-col .el-select__wrapper.is-focus) { box-shadow: 0 0 0 2px #0D2D6B inset; }
:deep(.rf-form-col .el-input__wrapper:hover),
:deep(.rf-form-col .el-select__wrapper:hover) { box-shadow: 0 0 0 1px #3B82F6 inset; }
:deep(.rf-form-col .el-input__wrapper),
:deep(.rf-form-col .el-select__wrapper) { min-height: 30px; }
:deep(.rf-form-col .el-input-number) { width: 100%; }
:deep(.rf-form-col .el-input-number .el-input__wrapper) { min-height: 30px; }
:deep(.rf-form-col .el-textarea__inner) { min-height: 28px !important; border-radius: 8px; }

/* ── Stepper ── */
.rf-stepper {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: .5rem;
  padding: 0;
}
.rf-step {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex: 1;
  position: relative;
}
.rf-step:not(:last-child)::after {
  content: '';
  flex: 1;
  height: 2px;
  margin: 0 .75rem;
  background: #E2E8F0;
  transition: background .3s ease;
}
.rf-step.active:not(:last-child)::after { background: linear-gradient(90deg, #0D2D6B, #3B82F6); }
.rf-step-num {
  display: grid;
  width: 1.4rem;
  height: 1.4rem;
  place-items: center;
  border-radius: 50%;
  font-size: .68rem;
  font-weight: 700;
  background: #E2E8F0;
  color: #94A3B8;
  flex-shrink: 0;
  transition: all .3s ease;
}
.rf-step.active .rf-step-num {
  background: linear-gradient(135deg, #0D2D6B, #16468E);
  color: #fff;
  box-shadow: 0 3px 10px rgba(13, 45, 107, .3);
}
.rf-step.current .rf-step-num {
  box-shadow: 0 0 0 4px rgba(13, 45, 107, .15);
}
.rf-step-label {
  font-size: .72rem;
  font-weight: 600;
  color: #94A3B8;
  white-space: nowrap;
  transition: color .3s ease;
}
.rf-step.active .rf-step-label { color: #0D2D6B; }

/* ── Sections ── */
.rf-section {
  background: #fff;
  border: 1px solid #E2E8F0;
  border-top: 3px solid #0D2D6B;
  border-radius: 10px;
  padding: .6rem .75rem;
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 2px 12px rgba(13, 45, 107, .06);
}
.rf-section-header {
  display: flex;
  align-items: center;
  gap: .4rem;
  margin-bottom: .4rem;
  padding-bottom: .3rem;
  border-bottom: 1px solid #F1F5F9;
}
.rf-section-header svg { color: #0D2D6B; }
.rf-section-header h3 {
  margin: 0;
  font-size: .78rem;
  font-weight: 700;
  color: #0D2D6B;
}

/* ── Grid ── */
.rf-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: .35rem .75rem;
}
.rf-col-1 { grid-column: span 1; }
.rf-col-2 { grid-column: span 2; }
.rf-col-3 { grid-column: span 3; }
.rf-mt { margin-top: .4rem; }

/* ── Subsection ── */
.rf-subsection {
  margin-top: .4rem;
  padding-top: .4rem;
  border-top: 1px solid #F1F5F9;
}
.rf-subsection-header {
  display: flex;
  align-items: center;
  gap: .4rem;
  margin-bottom: .3rem;
}
.rf-subsection-header svg { color: #0D2D6B; }
.rf-subsection-header h4 {
  margin: 0;
  font-size: .78rem;
  font-weight: 600;
  color: #0D2D6B;
  text-transform: uppercase;
  letter-spacing: .03em;
}

/* ── Diagnósticos ── */
.rf-dx-block {
  background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
  border: 1px solid #93C5FD;
  border-radius: 8px;
  padding: .4rem .6rem;
}
.rf-dx-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .25rem;
}
.rf-dx-title {
  font-size: .78rem;
  font-weight: 600;
  color: #1E40AF;
}
.rf-req { color: #DC2626; }
.rf-dx-add {
  font-size: .72rem;
  font-weight: 600;
  color: #0D2D6B;
  border-color: #0D2D6B;
  background: #DBEAFE;
}
.rf-dx-row {
  display: flex;
  align-items: center;
  gap: .4rem;
  margin-bottom: .25rem;
}
.rf-dx-row:last-child { margin-bottom: 0; }
.rf-dx-code { width: 140px; flex-shrink: 0; }
.rf-dx-desc { flex: 1; }
.rf-dx-remove {
  display: grid;
  width: 28px;
  height: 28px;
  place-items: center;
  border: 1px solid #FECACA;
  border-radius: 8px;
  background: #FEF2F2;
  color: #DC2626;
  cursor: pointer;
  flex-shrink: 0;
  transition: all .2s ease;
}
.rf-dx-remove:hover { background: #DC2626; color: #fff; border-color: #DC2626; }
.rf-dx-empty { font-size: .72rem; color: #94A3B8; margin: .25rem 0 0; }

/* ── Attachments ── */
.rf-attach {
  border: 1px solid #BFDBFE;
  border-radius: 8px;
  background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
  padding: .4rem;
  display: flex;
  flex-direction: column;
}
.rf-attach-active { border-color: #0D2D6B; box-shadow: 0 0 0 3px rgba(13, 45, 107, .08); }
.rf-attach-header {
  display: flex;
  align-items: center;
  gap: .3rem;
  margin-bottom: .25rem;
  font-size: .68rem;
  font-weight: 600;
  color: #475569;
}
.rf-attach-header svg { color: #2563EB; }
.rf-attach-count {
  margin-left: auto;
  font-size: .62rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: #E0E7FF;
  color: #3730A3;
}
.rf-dropzone {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .1rem;
  min-height: 48px;
  border: 2px dashed #60A5FA;
  border-radius: 6px;
  cursor: pointer;
  transition: all .2s ease;
  text-align: center;
  background: rgba(219, 234, 254, .3);
}
.rf-dropzone:hover { border-color: #0D2D6B; background: #DBEAFE; }
.rf-dropzone svg { color: #2563EB; }
.rf-dropzone p { margin: 0; font-size: .72rem; font-weight: 600; color: #1E40AF; }
.rf-dropzone small { font-size: .62rem; color: #94A3B8; }
.rf-file-list { margin-top: .25rem; display: flex; flex-direction: column; gap: .15rem; }
.rf-file-chip {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .3rem .5rem;
  border-radius: 6px;
  background: #F1F5F9;
  border: 1px solid #E2E8F0;
}
.rf-file-badge {
  font-size: .58rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: #DBEAFE;
  color: #1D4ED8;
  flex-shrink: 0;
}
.rf-file-name {
  flex: 1;
  font-size: .68rem;
  font-weight: 600;
  color: #475569;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rf-file-remove {
  display: grid;
  place-items: center;
  color: #94A3B8;
  cursor: pointer;
  border: none;
  background: none;
  padding: 2px;
  transition: color .2s ease;
}
.rf-file-remove:hover { color: #DC2626; }

/* ── Layout 2 columnas: formulario + panel lateral ── */
.rf-body {
  display: flex;
  gap: .75rem;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
.rf-form-col {
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

/* ── Panel lateral: resumen en tiempo real ── */
.rf-summary-panel {
  width: 260px;
  flex-shrink: 0;
  background: linear-gradient(180deg, #EFF6FF, #F8FAFC);
  border: 1px solid #BFDBFE;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.rf-summary-panel-header {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem .65rem;
  background: linear-gradient(135deg, #0D2D6B, #16468E);
  color: #fff;
  font-size: .72rem;
  font-weight: 700;
  border-radius: 10px 10px 0 0;
}
.rf-summary-panel-header svg { color: #fff; }
.rf-summary-panel-body {
  padding: .5rem .65rem;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: .4rem;
}
.rf-sp-block {
  display: flex;
  flex-direction: column;
  gap: .05rem;
  padding-bottom: .35rem;
  border-bottom: 1px solid #DBEAFE;
}
.rf-sp-block:last-child { border-bottom: none; padding-bottom: 0; }
.rf-sp-label {
  font-size: .58rem;
  font-weight: 700;
  color: #64748B;
  text-transform: uppercase;
  letter-spacing: .04em;
}
.rf-sp-value {
  font-size: .72rem;
  font-weight: 700;
  color: #0D2D6B;
  word-break: break-word;
}
.rf-sp-sub {
  font-size: .62rem;
  color: #3B82F6;
  font-weight: 500;
}
.rf-sp-dx-list { display: flex; flex-direction: column; gap: .15rem; margin-top: .1rem; }
.rf-sp-dx-item {
  display: flex;
  align-items: baseline;
  gap: .3rem;
  padding: .15rem .3rem;
  border-radius: 4px;
  background: rgba(255, 255, 255, .7);
}
.rf-sp-dx-code {
  font-size: .62rem;
  font-weight: 800;
  color: #15803D;
  font-family: monospace;
  flex-shrink: 0;
}
.rf-sp-dx-desc { font-size: .62rem; color: #166534; }
.rf-sp-empty { font-size: .62rem; color: #94A3B8; font-style: italic; }

/* ── Dialog de confirmación ── */
:deep(.confirm-dialog) {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(13, 45, 107, .25);
}
:deep(.confirm-dialog .el-dialog__header) {
  display: none;
}
:deep(.confirm-dialog .el-dialog__body) {
  padding: 0;
}
.confirm-body {
  padding: 1.5rem 1.5rem 1rem;
  text-align: center;
}
.confirm-icon-wrap {
  display: inline-grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
  color: #0D2D6B;
  margin-bottom: .75rem;
}
.confirm-title {
  margin: 0 0 .25rem;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0D2D6B;
}
.confirm-subtitle {
  margin: 0 0 1rem;
  font-size: .78rem;
  color: #64748B;
}
.confirm-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .5rem .75rem;
  text-align: left;
}
.confirm-item {
  display: flex;
  flex-direction: column;
  gap: .1rem;
  padding: .4rem .6rem;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
}
.confirm-item-full {
  grid-column: span 2;
}
.confirm-item span {
  font-size: .62rem;
  font-weight: 600;
  color: #94A3B8;
  text-transform: uppercase;
  letter-spacing: .03em;
}
.confirm-item strong {
  font-size: .75rem;
  font-weight: 700;
  color: #0D2D6B;
  word-break: break-word;
}
.confirm-dx-list {
  display: flex;
  flex-direction: column;
  gap: .2rem;
}
.confirm-dx-item {
  display: flex;
  align-items: baseline;
  gap: .4rem;
  padding: .2rem .4rem;
  border-radius: 6px;
  background: #F0FDF4;
  border: 1px solid #BBF7D0;
}
.confirm-dx-code {
  font-size: .68rem;
  font-weight: 800;
  color: #15803D;
  font-family: monospace;
  flex-shrink: 0;
}
.confirm-dx-desc { font-size: .68rem; color: #166534; }
.confirm-footer {
  display: flex;
  gap: .75rem;
  padding: 0 1.5rem 1.25rem;
}

/* ── Footer buttons ── */
.rf-footer {
  display: flex;
  gap: .5rem;
  margin-top: .4rem;
  padding-top: .4rem;
  border-top: 1px solid #E2E8F0;
  flex-shrink: 0;
}
.rf-btn-back {
  flex: 1;
  border-radius: 8px;
  font-weight: 600;
  color: #475569;
  border-color: #CBD5E1;
  background: #fff;
}
.rf-btn-back:hover { color: #0D2D6B; border-color: #0D2D6B; background: #EFF6FF; }
.rf-btn-next {
  flex: 1;
  border-radius: 8px;
  font-weight: 600;
  background: linear-gradient(135deg, #0D2D6B, #16468E) !important;
  border-color: #0D2D6B !important;
  box-shadow: 0 4px 12px rgba(13, 45, 107, .25);
}
.rf-btn-next:hover { background: linear-gradient(135deg, #16468E, #1A4A9E) !important; border-color: #16468E !important; box-shadow: 0 6px 16px rgba(13, 45, 107, .35); }
.rf-btn-submit {
  flex: 1;
  border-radius: 8px;
  font-weight: 600;
  background: linear-gradient(135deg, #0D2D6B, #16468E) !important;
  border-color: #0D2D6B !important;
  box-shadow: 0 4px 12px rgba(13, 45, 107, .25);
}
.rf-btn-submit:hover { background: linear-gradient(135deg, #16468E, #1A4A9E) !important; border-color: #16468E !important; box-shadow: 0 6px 16px rgba(13, 45, 107, .35); }

/* ── Card dx (detalle modal) ── */
.card-dx-list { display: flex; flex-direction: column; gap: 6px; margin-top: 4px; }
.card-dx-item {
  display: flex; align-items: baseline; gap: 6px;
  padding: 4px 8px; border-radius: 6px;
  background: #f5f3ff;
}
.card-dx-code {
  font-size: 11px; font-weight: 800; color: #7c3aed;
  font-family: monospace; flex-shrink: 0;
}
.card-dx-desc { font-size: 11px; color: #334155; }

@media (max-height: 750px) {
  :global(.request-dialog .el-dialog__body) { padding: .35rem .75rem .5rem; }
  .rf-section { padding: .4rem .5rem; }
  .rf-grid { gap: .25rem .5rem; }
  .rf-stepper { margin-bottom: .35rem; }
  .rf-step-num { width: 1.25rem; height: 1.25rem; font-size: .62rem; }
  .rf-step-label { font-size: .68rem; }
  :deep(.rf-form-col .el-input__wrapper),
  :deep(.rf-form-col .el-select__wrapper) { min-height: 28px; }
}

/* ── Modal Éxito ── */
:deep(.exito-dialog) { border-radius: 20px; overflow: hidden; box-shadow: 0 28px 70px rgba(11, 35, 73, .35); }
:deep(.exito-dialog .el-dialog__header) { display: none; }
:deep(.exito-dialog .el-dialog__body) { padding: 0; }
.exito-content {
  padding: 2rem 1.8rem 1.6rem;
  text-align: center;
  background: linear-gradient(180deg, #f0f5ff 0%, #f8faff 40%, #ffffff 100%);
}
.exito-icon-circle {
  width: 64px; height: 64px;
  margin: 0 auto 1rem;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff;
  box-shadow: 0 8px 24px rgba(34, 197, 94, .3);
  animation: exito-pop .4s cubic-bezier(.22, 1, .36, 1);
}
@keyframes exito-pop {
  0% { transform: scale(0); opacity: 0; }
  60% { transform: scale(1.15); }
  100% { transform: scale(1); opacity: 1; }
}
.exito-title { font-size: 1.3rem; font-weight: 800; color: #0d2d5e; margin: 0 0 .3rem; }
.exito-subtitle { font-size: .8rem; color: #61748d; margin: 0 0 1.3rem; }
.exito-details {
  text-align: left;
  background: #fff;
  border: 1px solid #e0e8f5;
  border-radius: 14px;
  padding: .8rem 1rem;
  margin-bottom: 1.3rem;
}
.exito-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .35rem 0;
  border-bottom: 1px solid #f0f4fa;
  font-size: .78rem;
}
.exito-detail-row:last-child { border-bottom: none; }
.exito-detail-row span { color: #61748d; font-weight: 600; }
.exito-detail-row strong { color: #0d2d5e; font-weight: 700; text-align: right; max-width: 60%; word-break: break-word; }
.exito-btn { border-radius: 8px; font-weight: 600; height: 42px; background: #0D2D6B !important; border-color: #0D2D6B !important; }
.exito-btn:hover { background: #16468E !important; border-color: #16468E !important; }

/* ── Modal Detalle ── */
:deep(.detalle-dialog) {
  border-radius: 22px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(11, 35, 73, .4), 0 0 0 1px rgba(255,255,255,.08);
}
:deep(.detalle-dialog .el-dialog__header) { display: none; }
:deep(.detalle-dialog .el-dialog__body) {
  padding: 0;
  max-height: calc(100vh - 3rem);
  overflow-y: auto;
  overflow-x: hidden;
  background: #F8FAFC;
}


.detalle-content { padding: 0; position: relative; }
.detalle-content > .grid,
.detalle-content > .detalle-code-bar,
.detalle-content > .detalle-respuesta,
.detalle-content > .detalle-negacion { padding-left: 1rem; padding-right: 1rem; margin-top: .8rem; }
.detalle-content > .grid:last-child { padding-bottom: 1rem; }

.detalle-close-btn {
  position: absolute;
  top: .8rem; right: .9rem;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 30px; height: 30px;
  border-radius: 50%;
  border: 1px solid #E2E8F0;
  background: #fff;
  color: #94A3B8;
  cursor: pointer;
  transition: all .2s ease;
}
.detalle-close-btn:hover { color: #DC2626; border-color: #FCA5A5; background: #FEF2F2; }

/* Header claro */
.detalle-head {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .7rem 2.6rem .7rem 1.1rem;
  background: #fff;
  border-bottom: 1px solid #EEF2F9;
}
.detalle-head-pattern {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(79,70,229,.06) 1.4px, transparent 1.4px);
  background-size: 15px 15px;
  -webkit-mask-image: linear-gradient(115deg, rgba(0,0,0,.9), transparent 70%);
  mask-image: linear-gradient(115deg, rgba(0,0,0,.9), transparent 70%);
  pointer-events: none;
}
.detalle-head-icon {
  position: relative; z-index: 1;
  width: 36px; height: 36px;
  border-radius: 11px;
  background: linear-gradient(135deg, #4F46E5, #7C3AED);
  display: grid; place-items: center;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 6px 16px rgba(79, 70, 229, .3);
}
.detalle-head-info { position: relative; z-index: 1; flex: 1; min-width: 0; }
.detalle-head-title { margin: 0; color: #0F172A; font-size: 1rem; font-weight: 800; }
.detalle-head-sub { margin: .15rem 0 0; color: #64748b; font-size: .7rem; }

.detalle-head-badge {
  position: relative; z-index: 1;
  padding: .3rem .7rem;
  border-radius: 999px;
  font-size: .65rem;
  font-weight: 700;
  flex-shrink: 0;
  display: inline-flex; align-items: center; gap: .3rem;
}
.detalle-head-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.detalle-head-badge.estado-pendiente { background: #fef3c7; color: #b45309; }
.detalle-head-badge.estado-en_espera { background: #dbeafe; color: #1d4ed8; }
.detalle-head-badge.estado-completado { background: #e0e7ff; color: #4338ca; }
.detalle-head-badge.estado-negado { background: #fee2e2; color: #b91c1c; }

/* Cards */
.detalle-card {
  background: #fff;
  border: 1px solid #d4deea;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(22,70,142,.07);
  transition: box-shadow .25s ease, border-color .25s ease, transform .25s ease;
  position: relative;
}
.detalle-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, #0d2d6b, #16468e, #2f70bb);
  opacity: .7;
}
.detalle-card:hover {
  box-shadow: 0 10px 28px rgba(22,70,142,.14);
  border-color: #b8c8de;
  transform: translateY(-1px);
}

.card-icon {
  width: 1.8rem; height: 1.8rem;
  border-radius: 8px;
  display: grid; place-items: center;
  flex-shrink: 0;
  margin: .6rem .6rem 0 .6rem;
  float: left;
}
.card-body { padding: .6rem .8rem .6rem .6rem; }
.card-title {
  margin: 0 0 .35rem;
  font-size: .72rem;
  font-weight: 800;
  color: #0d2d5e;
  text-transform: uppercase;
  letter-spacing: .04em;
  padding-top: .15rem;
}
.card-text { font-size: .72rem; color: #334e70; line-height: 1.5; margin: 0; }
.card-text-sm { font-size: .66rem; color: #64748b; line-height: 1.45; margin: 0; }
.card-text-clamp {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.leer-mas-btn {
  margin-top: .3rem;
  font-size: .62rem;
  font-weight: 700;
  color: #2563eb;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}
.leer-mas-btn:hover { text-decoration: underline; }

:deep(.historia-dialog) { border-radius: 18px; overflow: hidden; box-shadow: 0 32px 80px rgba(11,35,73,.35); }
:deep(.historia-dialog .el-dialog__header) { margin: 0; padding: 1rem 1.25rem; background: linear-gradient(135deg, #0D2D6B, #16468E); }
:deep(.historia-dialog .el-dialog__body) { padding: 1.25rem; }
:deep(.historia-dialog .el-dialog__headerbtn) { top: 14px; right: 14px; }
:deep(.historia-dialog .el-dialog__headerbtn .el-dialog__close) { color: #fff; }
.historia-dialog-header { display: flex; align-items: center; gap: .7rem; color: #fff; }
.historia-dialog-header > svg { color: #7dd3fc; flex-shrink: 0; }
.historia-dialog-header p { margin: 0; font-size: 14px; font-weight: 800; }
.historia-dialog-header span { display: block; margin-top: 2px; color: rgba(255,255,255,.65); font-size: 11px; }
.historia-dialog-text { max-height: min(55vh, 520px); margin: 0; overflow-y: auto; white-space: pre-wrap; word-break: break-word; color: #334155; font-size: 13px; line-height: 1.7; }

.data-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: .4rem;
  padding: .18rem 0;
  border-bottom: 1px solid #f1f5f9;
}
.data-row:last-child { border-bottom: none; }
.data-row span { font-size: .62rem; color: #94a3b8; white-space: nowrap; }
.data-row strong { font-size: .68rem; color: #1e293b; font-weight: 600; text-align: right; }

/* Código de aceptación */
.detalle-code-bar {
  display: flex;
  align-items: center;
  gap: .5rem;
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  border: 1px solid #a7f3d0;
  border-radius: 12px;
  padding: .55rem .8rem;
  margin-bottom: .6rem;
}
.detalle-code-value {
  margin-left: auto;
  font-family: monospace;
  font-size: .9rem;
  font-weight: 800;
  color: #166534;
  background: #fff;
  padding: .2rem .6rem;
  border-radius: 6px;
  border: 1px solid #86efac;
}

/* Respuesta / Negación */
.detalle-respuesta {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: .55rem .8rem;
  margin-bottom: .6rem;
}
.detalle-negacion {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
  padding: .55rem .8rem;
  margin-bottom: .6rem;
}

/* Timeline */
.timeline-item {
  display: flex;
  gap: .5rem;
  align-items: flex-start;
  padding: .15rem 0;
}
.timeline-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #16468e;
  margin-top: 5px;
  flex-shrink: 0;
}

/* Adjuntos */
.attachments-card {
  border: 2px dashed #cbd5e1;
  background: #f8fafc;
  transition: all 0.2s ease;
}
.attachments-card.dropzone-active {
  border-color: #16468e;
  background: #eff6ff;
  box-shadow: 0 0 0 3px rgba(22, 70, 142, 0.1);
}
.attach-header-icon {
  width: 24px; height: 24px;
  border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  background: #e0e8f5;
  color: #16468e;
  flex-shrink: 0;
}
.dropzone {
  border: 2px dashed #cbd5e1;
  background: #fff;
  transition: all 0.2s ease;
}
.dropzone:hover {
  border-color: #16468e;
  background: #f0f7ff;
}
.dropzone-circle {
  width: 36px; height: 36px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #e0e8f5 0%, #cdd9ee 100%);
  color: #16468e;
  transition: all 0.2s ease;
}
.dropzone:hover .dropzone-circle {
  background: linear-gradient(135deg, #16468e 0%, #0D2D6B 100%);
  color: #fff;
  transform: scale(1.08);
}
.file-chip {
  background: #fff;
  border: 1px solid #e2e8f0;
  transition: all 0.15s ease;
}
.file-chip:hover {
  border-color: #94a3b8;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}
.file-type-badge {
  font-size: 8px;
  font-weight: 800;
  color: #fff;
  padding: 3px 6px;
  border-radius: 5px;
  flex-shrink: 0;
  letter-spacing: 0.5px;
}
.ft-pdf { background: #dc2626; }
.ft-img { background: #2563eb; }
.ft-doc { background: #1d4ed8; }
.ft-xls { background: #16a34a; }
.ft-ppt { background: #ea580c; }
.ft-zip { background: #7c3aed; }
.ft-default { background: #64748b; }
.file-remove-btn {
  color: #cbd5e1;
  transition: color 0.15s ease;
  flex-shrink: 0;
  display: flex; align-items: center;
}
.file-remove-btn:hover { color: #ef4444; }

/* File list transition */
.file-list-enter-active, .file-list-leave-active {
  transition: all 0.25s ease;
}
.file-list-enter-from {
  opacity: 0;
  transform: translateX(-12px);
}
.file-list-leave-to {
  opacity: 0;
  transform: translateX(12px);
}

.adjunto-item { padding: .15rem 0; }
.adjunto-item:not(:last-child) { border-bottom: 1px solid #f1f5f9; }
.adjunto-link {
  display: flex;
  align-items: center;
  gap: .4rem;
  text-decoration: none;
  padding: .2rem 0;
  transition: opacity .15s ease;
}
.adjunto-link:hover { opacity: .7; }
.adjunto-icon {
  font-size: .52rem;
  font-weight: 800;
  color: #fff;
  padding: .12rem .3rem;
  border-radius: 4px;
  flex-shrink: 0;
}
.adjunto-pdf { background: #dc2626; }
.adjunto-img { background: #2563eb; }
.adjunto-doc { background: #1d4ed8; }
.adjunto-xls { background: #16a34a; }
.adjunto-ppt { background: #ea580c; }
.adjunto-zip { background: #7c3aed; }
.adjunto-default { background: #64748b; }
.adjunto-name {
  font-size: .66rem;
  color: #334e70;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.adjunto-empty {
  font-size: .66rem;
  color: #cbd5e1;
  font-style: italic;
  margin: 0;
  padding: .3rem 0;
}

.ph-dashboard {
  background:
    radial-gradient(ellipse at 85% -10%, rgba(59, 130, 246, 0.12), transparent 32rem),
    radial-gradient(ellipse at 5% 110%, rgba(16, 185, 129, 0.08), transparent 30rem),
    linear-gradient(165deg, #f6f8fc 0%, #eef2fb 45%, #f4f8fd 100%);
}

/* ── Stat cards ── */
/* El marcado y estilos de la tarjeta viven ahora en components/ui/StatCard.vue (variante `dense`) */

/* ── Distribución + charts ── */
.distrib-card {
  background: linear-gradient(145deg, #ffffff 0%, #f8fafe 100%) !important;
  border: 1px solid #dbe4f0 !important;
  box-shadow: 0 10px 30px rgba(22,70,142,.10) !important;
  position: relative;
  overflow: hidden;
  transition: transform .3s cubic-bezier(.22,1,.36,1), box-shadow .3s cubic-bezier(.22,1,.36,1);
}
.dark .distrib-card {
  background: #161b28 !important;
  border: 1px solid rgba(255,255,255,0.06) !important;
  box-shadow: 0 10px 30px rgba(0,0,0,.25) !important;
}
.distrib-card-glow {
  position: absolute; top: -40px; right: -40px; width: 160px; height: 160px;
  border-radius: 50%; pointer-events: none;
  background: radial-gradient(circle, rgba(126,179,255,0.20), transparent 70%);
}
.distrib-card:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(99,102,241,.18) !important; }
.distrib-card-donut { overflow: visible !important; }
.distrib-card-donut :deep(.apexcharts-canvas) { overflow: visible !important; }
.distrib-segment { position: relative; cursor: pointer; transition: opacity .2s ease; }
.distrib-segment:hover { opacity: .85; }
.distrib-segment::after {
  content: attr(data-tooltip); position: absolute; bottom: calc(100% + 6px); left: 50%;
  transform: translateX(-50%); background: #1e2d55; color: #fff;
  font-size: 10px; font-weight: 600; padding: 4px 8px; border-radius: 6px;
  white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: 20;
}
.distrib-segment:hover::after { opacity: 1; }

.donut-chart {
  width: 72px; height: 72px; border-radius: 50%;
  position: relative;
  transition: transform .3s ease;
  box-shadow: 0 4px 14px rgba(22,70,142,.14);
}
.donut-chart:hover { transform: scale(1.08) rotate(5deg); }
.donut-center {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  width: 52px; height: 52px; border-radius: 50%; background: #fff;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  box-shadow: inset 0 2px 6px rgba(22,70,142,.08);
}
.donut-num { font-size: 16px; font-weight: 800; color: #1e2d55; line-height: 1; }
.donut-label { font-size: 8px; color: #8a9ab5; margin-top: 2px; text-transform: uppercase; letter-spacing: .05em; }

/* ── Donut de estados (ApexCharts) con total centrado ── */
.estado-donut-wrap {
  position: relative;
  width: 112px;
  height: 112px;
  flex-shrink: 0;
}
.estado-donut-center {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  animation: fadeInScale .5s ease .3s both;
}
.estado-donut-num {
  font-size: 20px;
  font-weight: 900;
  letter-spacing: -.03em;
  color: #0d2d6b;
  line-height: 1;
}
.estado-donut-label {
  font-size: 9px;
  font-weight: 700;
  color: #8a9ab5;
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-top: 2px;
}
.dark .estado-donut-num { color: #e2e8f0; }
.dark .estado-donut-label { color: #64748b; }

/* ── Leyenda del donut (lista HTML) ── */
.donut-legend {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 96px;
}
.donut-legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 10px;
}
.donut-legend-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.donut-legend-label {
  flex: 1;
  min-width: 0;
  color: #475569;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.donut-legend-pct {
  font-weight: 800;
  color: #1e293b;
}
.donut-legend-count {
  color: #94a3b8;
  font-size: 9px;
  min-width: 14px;
  text-align: right;
}
.dark .donut-legend-label { color: #94a3b8; }
.dark .donut-legend-pct { color: #e2e8f0; }
.dark .donut-legend-count { color: #64748b; }
.donut-legend-item {
  border-radius: 6px;
  padding: 2px 4px;
  margin: 0 -4px;
  transition: background 0.2s ease, transform 0.2s ease;
}
.donut-legend-item:hover {
  background: rgba(99, 102, 241, 0.08);
  transform: translateX(2px);
}
.donut-legend-item:hover .donut-legend-dot {
  transform: scale(1.3);
}
.donut-legend-dot { transition: transform 0.2s ease; }

/* ── Landing de bienvenida (foto de fondo) ── */
.landing-hero {
  position: relative;
  border-radius: 18px;
  overflow: hidden;
  background: linear-gradient(160deg, #f4f8ff 0%, #ffffff 55%, #eff4fe 100%);
  border: 1px solid #e3eaf4;
  box-shadow: 0 10px 30px rgba(22,70,142,.08);
}
.dark .landing-hero {
  background: #161b28;
  border-color: rgba(255,255,255,0.06);
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
}

/* Banner de la clínica de fondo completo con velo claro a la izquierda */
.landing-photo {
  position: absolute;
  inset: 0;
  background: url('/images/clinica-hero-banner.png?v=3') center / cover no-repeat;
  pointer-events: none;
}
.landing-photo::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg,
    rgba(255,255,255,0.97) 0%,
    rgba(255,255,255,0.92) 16%,
    rgba(255,255,255,0.55) 27%,
    rgba(255,255,255,0.12) 36%,
    transparent 44%);
}
.dark .landing-photo::after {
  background: linear-gradient(90deg,
    rgba(22,27,40,0.97) 0%,
    rgba(22,27,40,0.92) 16%,
    rgba(22,27,40,0.55) 27%,
    rgba(22,27,40,0.12) 36%,
    transparent 44%);
}

.landing-inner {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 100%;
  height: 100%;
  padding: clamp(0.9rem, 2.4vh, 1.6rem) clamp(1.25rem, 2.6vw, 2.4rem);
}
.landing-inner > * {
  animation: heroRise .55s cubic-bezier(.22,1,.36,1) both;
}
.landing-inner > *:nth-child(1) { animation-delay: .05s; }
.landing-inner > *:nth-child(2) { animation-delay: .16s; }
.landing-inner > *:nth-child(3) { animation-delay: .3s; }
@keyframes heroRise {
  from { opacity: 0; transform: translateY(14px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Marca arriba a la izquierda */
.landing-brand {
  display: flex;
  align-items: center;
  gap: 11px;
}
.landing-logo-circle {
  width: 54px; height: 54px;
  border-radius: 50%;
  background: #ffffff;
  border: 1px solid #e3eaf4;
  box-shadow: 0 0 0 5px rgba(46,110,224,0.07), 0 8px 20px rgba(22,70,142,.14);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}
.landing-logo-circle img { width: 42px; height: 42px; object-fit: contain; }
.dark .landing-logo-circle {
  background: #1d2432;
  border-color: rgba(255,255,255,0.08);
  box-shadow: 0 0 0 5px rgba(126,166,240,0.08), 0 8px 20px rgba(0,0,0,.35);
}
.landing-brand-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.landing-brand-text strong {
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: #0d2d6b;
  line-height: 1.15;
}
.landing-brand-text span {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: .2em;
  text-transform: uppercase;
  color: #94a3b8;
}
.dark .landing-brand-text strong { color: #e2e8f0; }
.dark .landing-brand-text span { color: #64748b; }

/* Bloque central: título + desc + CTA */
.landing-main {
  margin: auto 0;
  max-width: 560px;
}
.landing-title {
  margin: 0;
  font-size: clamp(1.6rem, 2.9vw, 2.6rem);
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1.14;
  color: #16468e;
  text-shadow: 0 1px 2px rgba(255,255,255,.85), 0 0 14px rgba(255,255,255,.6);
}
.landing-title span { color: #2563eb; }
.dark .landing-title { color: #e2e8f0; }
.dark .landing-title span { color: #7ea6f0; }

.landing-desc {
  margin: 10px 0 0;
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.55;
  color: #3d4f6e;
  max-width: 460px;
  text-shadow: 0 1px 2px rgba(255,255,255,.8);
}
.dark .landing-desc { color: #94a3b8; }

.landing-cta {
  position: relative;
  overflow: hidden;
  margin-top: clamp(0.9rem, 2.2vh, 1.5rem);
  display: inline-flex;
  align-items: center;
  gap: 9px;
  padding: 13px 24px;
  border-radius: 12px;
  border: none;
  background: #2563eb;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 10px 24px rgba(37,99,235,.35), inset 0 1px 0 rgba(255,255,255,0.18);
  transition: transform .18s ease, box-shadow .18s ease;
}
.landing-cta::after {
  content: '';
  position: absolute;
  top: 0; left: -80%;
  width: 55%; height: 100%;
  background: linear-gradient(100deg, transparent, rgba(255,255,255,0.28), transparent);
  transform: skewX(-18deg);
  transition: left .55s ease;
}
.landing-cta:hover {
  transform: translateY(-2px);
  background: #1d4ed8;
  box-shadow: 0 14px 30px rgba(37,99,235,.45), inset 0 1px 0 rgba(255,255,255,0.18);
}
.landing-cta:hover::after { left: 125%; }
.landing-cta:active { transform: translateY(0); }
.landing-cta-arrow { transition: transform .2s ease; }
.landing-cta:hover .landing-cta-arrow { transform: translateX(3px); }


@media (max-width: 900px) {
  .landing-photo::after {
    background: rgba(255,255,255,0.88);
  }
  .dark .landing-photo::after {
    background: rgba(22,27,40,0.9);
  }
}

@media (max-width: 1023px) {
  .ph-dashboard > div:first-child {
    min-height: 0;
  }
  .landing-hero {
    flex: 1;
    min-height: 0;
  }
}

@media (max-width: 640px) {
  .landing-inner { padding: 1rem; }
  .landing-logo-circle { width: 44px; height: 44px; }
  .landing-logo-circle img { width: 34px; height: 34px; }
  .landing-brand-text strong { font-size: 15px; }
  .landing-brand-text span { font-size: 7px; letter-spacing: .14em; }
  .landing-title { font-size: clamp(1.55rem, 7vw, 2rem); }
  .landing-desc { font-size: 12px; }
  .landing-cta { width: 100%; justify-content: center; padding: 12px 14px; font-size: 12px; }
}

@keyframes fadeInScale {
  from { opacity: 0; transform: translate(-50%, -50%) scale(.8); }
  to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
}

/* ── Chart header icon ── */
.chart-header-icon {
  width: 28px; height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ── Especialidades más solicitadas ── */
.esp-top-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  overflow: hidden;
}
.esp-top-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.esp-top-rank {
  width: 16px;
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 800;
  color: #b0bccf;
  text-align: center;
}
.esp-top-label {
  flex: 0 0 118px;
  min-width: 0;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.esp-top-bar-track {
  flex: 1;
  height: 8px;
  border-radius: 999px;
  background: #f1f5f9;
  overflow: hidden;
}
.dark .esp-top-bar-track { background: #1e293b; }
.esp-top-bar-fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #60a5fa, #3b82f6);
  transition: width 0.6s cubic-bezier(.22,1,.36,1);
}
.esp-top-bar-fill-top {
  background: linear-gradient(90deg, #a78bfa, #7c3aed);
}
.esp-top-count {
  width: 28px;
  flex-shrink: 0;
  text-align: right;
  font-size: 11px;
  font-weight: 800;
  color: #1e2d55;
}
.dark .esp-top-count { color: #e2e8f0; }

/* ── Ritmo de solicitudes (métrica única) ── */
.ritmo-metric {
  display: flex;
  align-items: baseline;
  gap: 6px;
}
.ritmo-metric-num {
  font-size: 2.1rem;
  font-weight: 800;
  line-height: 1;
  background: linear-gradient(135deg, #6d28d9, #a855f7);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.ritmo-metric-unit {
  font-size: .85rem;
  font-weight: 700;
  color: #94a3b8;
}
.ritmo-metric-total {
  font-size: .72rem;
  font-weight: 600;
  color: #64748b;
  margin: 0;
}
.ritmo-metric-pill {
  margin-top: 2px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: .68rem;
  font-weight: 800;
}
.ritmo-pill-up { background: #ecfdf5; color: #059669; }
.ritmo-pill-down { background: #fef2f2; color: #dc2626; }
.ritmo-sparkline-wrap {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 4px;
}
.ritmo-sparkline-wrap svg { width: 100%; height: 56px; }
.ritmo-sparkline-labels {
  display: flex;
  justify-content: space-between;
  font-size: 9px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .03em;
}

/* ── Trend stat pill ── */
.trend-stat-pill {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  font-size: 11px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 999px;
  background: linear-gradient(135deg, #dbeafe, #eff6ff);
  color: #2563c4;
  border: 1px solid #bfdbfe;
}
.trend-stat-pill svg { color: #2563c4; }

/* ── Quick strip buttons ── */

/* ── Distribution bars ── */
.dist-bar-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.dist-bar-label {
  font-size: 11px;
  font-weight: 800;
}
.dist-bar-count {
  font-size: 12px;
  font-weight: 800;
  color: #1e2d55;
  font-variant-numeric: tabular-nums;
}
.dist-bar-pct {
  font-size: 10px;
  font-weight: 600;
  color: #94a3b8;
}
.dist-bar-track {
  height: 8px;
  border-radius: 999px;
  background: #f1f5f9;
  overflow: hidden;
}
.dist-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.8s cubic-bezier(.22,1,.36,1);
  animation: barFillIn 0.8s cubic-bezier(.22,1,.36,1) both;
}
@keyframes barFillIn {
  from { width: 0 !important; }
}

.quick-strip-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: 7px 14px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
}
.quick-strip-btn:hover {
  transform: translateY(-2px);
  filter: brightness(1.05);
}
.quick-strip-btn:active { transform: translateY(0); }
.quick-strip-blue {
  background: linear-gradient(135deg, #0D2D6B, #16468E);
  color: #fff;
  box-shadow: 0 4px 14px rgba(13,45,107,.25);
}
.quick-strip-green {
  background: linear-gradient(135deg, #dcfce7, #bbf7d0);
  color: #15966a;
  box-shadow: 0 4px 14px rgba(21,150,106,.12);
}
.quick-strip-purple {
  background: linear-gradient(135deg, #ede9fe, #ddd6fe);
  color: #7c3aed;
  box-shadow: 0 4px 14px rgba(124,58,237,.1);
}

/* ── KPI rows ── */
.kpi-row {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .55rem .7rem;
  border-radius: 12px;
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  border: 1px solid #e2e8f0;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.kpi-row:hover {
  transform: translateX(3px);
  box-shadow: 0 4px 14px rgba(22,70,142,.08);
  border-color: #cbd5e1;
}
.kpi-icon {
  width: 32px; height: 32px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-label {
  font-size: 11px;
  font-weight: 600;
  color: #475569;
  margin: 0;
}
.kpi-value {
  font-size: 18px;
  font-weight: 900;
  letter-spacing: -.02em;
  font-variant-numeric: tabular-nums;
}

/* ── Mini ref rows (últimas referencias ampliado) ── */
.mini-ref-row {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .5rem .65rem;
  border-radius: 10px;
  background: #fff;
  border: 1px solid #e8eef6;
  border-left: 3px solid var(--ref-color, #94a3b8);
  cursor: pointer;
  width: 100%;
  text-align: left;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
  animation: rowIn 0.4s cubic-bezier(.22,1,.36,1) both;
}
.mini-ref-row:hover {
  transform: translateX(3px);
  box-shadow: 0 6px 16px rgba(22,70,142,.1);
  border-color: #b9c8e0;
  background: #f8fbff;
}
.mini-ref-avatar {
  width: 32px; height: 32px;
  border-radius: 9px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  background: linear-gradient(135deg, color-mix(in srgb, var(--ref-color, #94a3b8) 85%, #fff), var(--ref-color, #94a3b8));
  box-shadow: 0 3px 8px color-mix(in srgb, var(--ref-color, #94a3b8) 35%, transparent);
  transition: transform .2s ease;
}
.mini-ref-row:hover .mini-ref-avatar { transform: scale(1.08); }
.mini-ref-row-sm { padding: .35rem .55rem; gap: .5rem; border-radius: 8px; }
.mini-ref-avatar-sm { width: 26px; height: 26px; border-radius: 7px; font-size: 9px; }
.mini-ref-name-sm { font-size: 11.5px; }
.mini-ref-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.mini-ref-name {
  font-size: 12.5px;
  font-weight: 700;
  color: #1e2d55;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mini-ref-meta {
  font-size: 10.5px;
  font-weight: 500;
  color: #94a3b8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: flex;
  align-items: center;
  gap: 5px;
}
.mini-ref-dot {
  color: #cbd5e1;
  flex-shrink: 0;
}
.mini-ref-badge {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  color: var(--ref-color, #94a3b8);
  background: color-mix(in srgb, var(--ref-color, #94a3b8) 14%, #fff);
  white-space: nowrap;
}

/* ── Tooltip flotante (igual al dashboard interno) ── */
.ref-tooltip-float {
  position: fixed;
  min-width: 220px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.2);
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  z-index: 9999;
  text-align: left;
  pointer-events: none;
  animation: refTooltipIn .15s ease;
}
@keyframes refTooltipIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.ref-tooltip-arrow {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  width: 0; height: 0;
  border: 6px solid transparent;
}
.ref-tooltip-arrow.arrow-down {
  top: 100%;
  border-top-color: #fff;
  filter: drop-shadow(0 2px 2px rgba(0,0,0,0.06));
}
.ref-tooltip-arrow.arrow-up {
  bottom: 100%;
  border-bottom-color: #fff;
  filter: drop-shadow(0 -2px 2px rgba(0,0,0,0.06));
}
.ref-tooltip-head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-bottom: 6px;
  margin-bottom: 2px;
  border-bottom: 1px solid #f1f5f9;
}
.ref-tooltip-avatar {
  width: 24px;
  height: 24px;
  border-radius: 7px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 800;
  color: #fff;
  background: var(--ref-color, #94a3b8);
}
.ref-tooltip-name {
  font-size: 12px;
  font-weight: 700;
  color: #1e2d55;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ref-tooltip-row {
  font-size: 11px;
  color: #64748b;
  line-height: 1.5;
}
.ref-tooltip-row strong {
  color: #475569;
  font-weight: 700;
}
.ref-tooltip-estado {
  color: var(--ref-color, #94a3b8);
  font-weight: 700;
}

/* ── Estado total badge ── */
.estado-total-badge {
  margin-left: auto;
  font-size: 10px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  background: #e0ecff;
  color: #16468e;
}

/* ── Estado bars (reemplazo del donut) ── */
.gauge-wrap {
  min-height: 120px;
  position: relative;
  z-index: 1;
}
.estado-bar-wrap {
  position: relative;
  transition: transform .25s ease;
}
.estado-bar-wrap:hover {
  transform: translateX(3px);
}
.estado-bar-dot {
  width: 8px; height: 8px;
  border-radius: 999px;
  flex-shrink: 0;
  box-shadow: 0 0 0 3px rgba(255,255,255,0.9);
  animation: barDotPulse 2.5s ease-in-out infinite;
}
@keyframes barDotPulse {
  0%, 100% { box-shadow: 0 0 0 3px rgba(255,255,255,0.9); }
  50% { box-shadow: 0 0 0 5px rgba(255,255,255,0.6); }
}
.estado-bar-label {
  font-size: 11px;
  font-weight: 600;
  color: #475569;
}
.estado-bar-value {
  font-size: 13px;
  font-weight: 800;
  transition: transform .25s ease;
}
.estado-bar-wrap:hover .estado-bar-value {
  transform: scale(1.15);
}
.estado-bar-track {
  height: 8px;
  border-radius: 999px;
  background: #f1f5f9;
  overflow: hidden;
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);
}
.estado-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 1s cubic-bezier(.22,1,.36,1);
  box-shadow: 0 1px 4px rgba(0,0,0,0.12);
  position: relative;
  overflow: hidden;
}
.estado-bar-shine {
  position: absolute;
  top: 0; left: -40%;
  width: 40%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
  animation: barShine 2.5s ease-in-out infinite;
}
@keyframes barShine {
  0% { left: -40%; }
  100% { left: 100%; }
}

/* ── Operations / Accesos rápidos ── */
.operations-grid {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(300px, 2fr);
  gap: .75rem;
  flex-shrink: 0;
}
.operations-card,
.flow-card {
  position: relative;
  overflow: hidden;
}
.operations-card {
  padding: 1rem;
  background: linear-gradient(145deg, #ffffff 0%, #f5f8ff 100%) !important;
  border: 1px solid #dbe4f0 !important;
  border-top: 3px solid #0D2D6B !important;
  box-shadow: 0 6px 20px rgba(22,70,142,.08) !important;
  transition: transform .35s cubic-bezier(.22,1,.36,1), box-shadow .35s cubic-bezier(.22,1,.36,1);
}
.operations-card-hover:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 36px rgba(22, 70, 142, .14) !important;
}
.operations-card::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 3px;
  background: linear-gradient(90deg, #0D2D6B, #3978cf, #8dbdff);
}
.operations-glow {
  position: absolute;
  right: -55px;
  bottom: -70px;
  width: 160px;
  height: 160px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(126, 179, 255, .16), transparent 70%);
  pointer-events: none;
  animation: orbitFloat 7s ease-in-out infinite;
}
.operations-heading,
.flow-heading {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  margin-bottom: .75rem;
}
.panel-eyebrow {
  margin: 0;
  color: #8a9ab5;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
}
.panel-title {
  margin: 2px 0 0;
  color: #1e2d55;
  font-size: 15px;
  font-weight: 800;
}
.operations-hint {
  color: #94a3b8;
  font-size: 9px;
  font-weight: 500;
  white-space: nowrap;
}

/* ── Action buttons (simplified) ── */
.action-btn {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  background: linear-gradient(145deg, #ffffff 0%, #f8fafe 100%);
  cursor: pointer;
  transition: transform .25s cubic-bezier(.22,1,.36,1), box-shadow .25s ease, border-color .25s ease;
  text-align: left;
  position: relative;
  overflow: hidden;
}
.action-btn::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--btn-color, #2563c4), var(--btn-color2, #60a5fa));
}
.action-btn-icon {
  width: 44px; height: 44px;
  border-radius: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: linear-gradient(135deg, var(--btn-color, #2563c4), var(--btn-color2, #60a5fa));
  box-shadow: 0 4px 14px color-mix(in srgb, var(--btn-color, #2563c4) 35%, transparent);
  flex-shrink: 0;
}
.action-btn-text {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.action-btn-text strong {
  font-size: 14px;
  font-weight: 800;
  color: #1e2d55;
}
.action-btn-text span {
  font-size: 12px;
  color: #94a3b8;
  margin-top: 2px;
}
.action-btn-arrow {
  color: #cbd5e1;
  transition: transform .2s ease, color .2s ease;
}
.action-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 30px color-mix(in srgb, var(--btn-color, #2563c4) 18%, transparent);
  border-color: var(--btn-color, #2563c4);
}
.action-btn:hover .action-btn-arrow {
  color: var(--btn-color, #2563c4);
  transform: translateX(4px);
}
.action-btn-blue { --btn-color: #2563c4; --btn-color2: #60a5fa; }
.action-btn-green { --btn-color: #15966a; --btn-color2: #4ade80; }
.action-btn-purple { --btn-color: #7048e8; --btn-color2: #c4b5fd; }

.quick-actions {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: .75rem;
}
.quick-action {
  --quick-color: #2563c4;
  --quick-bg: #e7efff;
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
  gap: .6rem;
  padding: .75rem;
  border: 1px solid rgba(226, 232, 240, .85);
  border-radius: .85rem;
  background: linear-gradient(145deg, #fff, #f8fafc);
  color: inherit;
  cursor: pointer;
  text-decoration: none;
  transition: transform .25s cubic-bezier(.22,1,.36,1), border-color .25s ease, box-shadow .25s ease;
}
.quick-action::after {
  content: '';
  position: absolute;
  inset: auto .8rem 0;
  height: 2px;
  border-radius: 999px 999px 0 0;
  background: var(--quick-color);
  opacity: 0;
  transform: scaleX(.4);
  transition: opacity .25s ease, transform .25s ease;
}
.quick-action:hover {
  transform: translateY(-3px);
  border-color: color-mix(in srgb, var(--quick-color) 30%, white);
  box-shadow: 0 10px 22px rgba(22, 70, 142, .11);
}
.quick-action:hover::after {
  opacity: 1;
  transform: scaleX(1);
}
.quick-action-green { --quick-color: #15966a; --quick-bg: #dcfce7; }
.quick-action-purple { --quick-color: #7048e8; --quick-bg: #ede9fe; }
.quick-action-icon {
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  border-radius: 10px;
  color: var(--quick-color);
  background: var(--quick-bg);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.75);
}
.quick-action-icon svg { width: 16px; height: 16px; }
.quick-action-copy {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  text-align: left;
}
.quick-action-copy strong {
  color: #1e2d55;
  font-size: 11px;
  font-weight: 800;
}
.quick-action-copy span {
  overflow: hidden;
  margin-top: 2px;
  color: #8a9ab5;
  font-size: 9px;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.quick-action-arrow {
  width: 12px;
  height: 12px;
  flex: 0 0 auto;
  color: #c3ccda;
  transition: color .2s ease, transform .2s ease;
}
.quick-action:hover .quick-action-arrow {
  color: var(--quick-color);
  transform: translateX(3px);
}
.flow-card {
  padding: 1rem;
  background: linear-gradient(145deg, #ffffff 0%, #f0fdf4 100%) !important;
  border: 1px solid #d1e7db !important;
  border-top: 3px solid #15966a !important;
  box-shadow: 0 6px 20px rgba(22,70,142,.08) !important;
  transition: transform .35s cubic-bezier(.22,1,.36,1), box-shadow .35s cubic-bezier(.22,1,.36,1);
}
.flow-card-hover:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 36px rgba(22, 70, 142, .14) !important;
}
.flow-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, #15966a, #4ade80, #86efac);
}
.flow-orbit {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  animation: orbitFloat 6s ease-in-out infinite;
}
.flow-orbit-one {
  top: -60px;
  right: -40px;
  width: 140px;
  height: 140px;
  background: radial-gradient(circle, rgba(74,222,128,.10), transparent 70%);
  animation-delay: 0s;
}
.flow-orbit-two {
  right: 30px;
  bottom: -70px;
  width: 100px;
  height: 100px;
  background: radial-gradient(circle, rgba(126,179,255,.08), transparent 70%);
  animation-delay: 2s;
}
@keyframes orbitFloat {
  0%, 100% { transform: translate(0, 0) scale(1); opacity: 1; }
  50% { transform: translate(-8px, 6px) scale(1.08); opacity: 0.7; }
}
.flow-content { position: relative; z-index: 1; }
.flow-eyebrow {
  margin: 0;
  color: #8a9ab5;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
}
.flow-heading h3 {
  margin: 2px 0 0;
  color: #1e2d55;
  font-size: 14px;
  font-weight: 800;
}
.flow-live {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .22rem .5rem;
  border: 1px solid rgba(74,222,128,.2);
  border-radius: 999px;
  background: #dcfce7;
  color: #15966a;
  font-size: 8px;
  font-weight: 600;
  white-space: nowrap;
}
.flow-live i {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 3px rgba(74,222,128,.16);
  animation: livePulse 2s ease-in-out infinite;
}
.flow-steps {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .4rem;
}
.flow-step {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: .4rem;
}
.flow-step-icon {
  width: 32px;
  height: 32px;
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: #e7efff;
  color: #2563c4;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.75);
  transition: transform .25s ease, box-shadow .25s ease;
}
.flow-step:hover .flow-step-icon {
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(37,99,196,.15);
}
.flow-step-icon svg { width: 15px; height: 15px; }
.flow-step-success {
  background: #dcfce7;
  color: #15966a;
}
.flow-step div:last-child {
  display: flex;
  min-width: 0;
  flex-direction: column;
}
.flow-step strong {
  color: #1e2d55;
  font-size: 10px;
  font-weight: 700;
}
.flow-step span {
  overflow: hidden;
  margin-top: 1px;
  color: #8a9ab5;
  font-size: 8px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.flow-connector {
  position: relative;
  min-width: 14px;
  height: 2px;
  flex: 1;
  overflow: hidden;
  border-radius: 999px;
  background: #e2e8f0;
}
.flow-connector span {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent, #2563c4, transparent);
  animation: flowMove 2.6s linear infinite;
}
@keyframes flowMove {
  from { transform: translateX(-100%); }
  to { transform: translateX(100%); }
}
@media (max-width: 1100px) {
  .operations-grid { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .quick-actions { grid-template-columns: 1fr; }
  .operations-hint { display: none; }
  .flow-steps { align-items: stretch; flex-direction: column; }
  .flow-connector { width: 1px; min-width: 1px; height: 10px; margin-left: 13px; flex: 0 0 auto; }
}

/* ── Filas tipo medicamento ── */
.med-row {
  background: #ffffff;
  border: 1px solid #e8eef6;
  border-left: 4px solid transparent;
  box-shadow: 0 2px 8px rgba(22, 70, 142, .05);
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
}
.med-row:hover {
  transform: translateX(4px);
  box-shadow: 0 10px 24px rgba(22, 70, 142, .12);
  border-color: #b9c8e0;
  background: #f8fbff;
}

.med-row--active {
  background: linear-gradient(135deg, #16468e, #0d2d6b);
  border-color: transparent;
  box-shadow: 0 12px 28px rgba(13, 45, 107, .32);
}
.med-row--active:hover { transform: translateX(3px); box-shadow: 0 16px 34px rgba(13, 45, 107, .40); }

.med-icon {
  background: linear-gradient(135deg, #eaf2fd, #dbe9fb);
  color: #16468e;
  font-size: .75rem;
  font-weight: 800;
  box-shadow: 0 3px 8px rgba(22, 70, 142, .12);
  transition: transform .2s ease, box-shadow .2s ease;
}
.med-row:hover .med-icon { transform: scale(1.08); }
.med-icon--active {
  background: rgba(255,255,255,.18);
  color: #fff;
}

.empty-state-icon {
  color: #2f70bb;
  background: #e4f0ff;
  box-shadow: inset 2px 2px 5px rgba(47, 112, 187, .12), inset -2px -2px 5px #fff;
}

.empty-state-action {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .6rem 1rem;
  color: #fff;
  background: linear-gradient(135deg, #0d2d6b, #2563eb);
  border: 0;
  border-radius: 12px;
  font-size: .8rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(13, 45, 107, .2);
  transition: transform .2s ease, box-shadow .2s ease;
}
.empty-state-action:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(13, 45, 107, .32); }

/* ── Entradas ── */
.anim-fade-down  { animation: fadeDown  0.5s cubic-bezier(.22,1,.36,1) both; }

/* ── Header solicitudes recientes ── */
.recent-card {
  background: linear-gradient(145deg, #ffffff 0%, #f8fafe 100%) !important;
  border: 1px solid #dbe4f0 !important;
  box-shadow: 0 10px 30px rgba(22,70,142,.10) !important;
}
.recent-header-icon {
  width: 36px; height: 36px;
  border-radius: 11px;
  background: linear-gradient(135deg, #0D2D6B, #2563eb);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
  animation: recentIconPulse 2.5s ease-in-out infinite;
}
@keyframes recentIconPulse {
  0%, 100% { box-shadow: 0 3px 10px rgba(13, 45, 107, 0.25); }
  50% { box-shadow: 0 3px 18px rgba(13, 45, 107, 0.40); }
}
.recent-count-badge {
  font-size: 11px;
  font-weight: 700;
  color: #16468e;
  background: #e0ecff;
  padding: 2px 8px;
  border-radius: 999px;
}
.recent-refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .4rem .8rem;
  border-radius: 9px;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: all .2s ease;
}
.recent-refresh-btn:hover {
  color: #16468e;
  background: #e0ecff;
  border-color: #bfdbfe;
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(22, 70, 142, .1);
}
.recent-refresh-btn:active {
  transform: translateY(0);
}
.anim-slide-up   { animation: slideUp   0.5s cubic-bezier(.22,1,.36,1) both; }
.anim-fade-left  { animation: fadeLeft  0.5s cubic-bezier(.22,1,.36,1) both; }
.anim-row-in     { animation: rowIn     0.4s cubic-bezier(.22,1,.36,1) both; }
.animate-fade-in-down { animation: fadeDown 0.5s cubic-bezier(.22,1,.36,1) both; }
.animate-fade-in-up   { animation: slideUp 0.5s cubic-bezier(.22,1,.36,1) both; }

@keyframes fadeDown  { from { opacity:0; transform:translateY(-18px); } to { opacity:1; transform:none; } }
@keyframes slideUp   { from { opacity:0; transform:translateY(20px);  } to { opacity:1; transform:none; } }
@keyframes fadeLeft  { from { opacity:0; transform:translateX(-20px); } to { opacity:1; transform:none; } }
@keyframes rowIn     { from { opacity:0; transform:translateX(-12px); } to { opacity:1; transform:none; } }

/* ── Skeleton loaders con shimmer ── */
.skeleton-row {
  background: #fff;
  border: 1px solid #edf1f7;
}
.shimmer-box,
.shimmer-bar {
  position: relative;
  overflow: hidden;
  background: #e6ebf3;
}
.shimmer-box { border-radius: 6px; }
.shimmer-bar { border-radius: 4px; }
.shimmer-box::after,
.shimmer-bar::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255,255,255,0.6) 50%,
    transparent 100%
  );
  animation: shimmer 1.8s ease-in-out infinite;
}
@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

/* ── Tooltip en distribución ── */
.distrib-segment {
  position: relative;
  cursor: pointer;
}
.distrib-segment::after {
  content: attr(data-tooltip);
  position: absolute;
  bottom: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  background: #1e2d55;
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 6px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity .2s ease;
  z-index: 20;
}
.distrib-segment:hover::after { opacity: 1; }

/* ── Contador ── */
.counter {
  transition: all 0.1s ease;
  font-variant-numeric: tabular-nums;
}

/* ── Responsive móvil ── */
@media (max-width: 640px) {
  .hero-card { padding: 1rem !important; }
  .hero-link { padding: .5rem .9rem !important; font-size: .72rem !important; }
  .hero-link span { display: none; }
  .distrib-card { padding: .75rem !important; }
  .distrib-card .flex.items-center.gap-4 { gap: .5rem !important; flex-wrap: wrap; }
  .stat-card { padding: .75rem !important; }
  .stat-value { font-size: 22px !important; }
  .operations-card, .flow-card { padding: .75rem !important; }
  .quick-actions { gap: .5rem !important; }
  .quick-action { padding: .5rem !important; }
}
</style>
