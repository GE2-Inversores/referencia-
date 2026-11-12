<template>
  <div class="inf-page h-full overflow-y-auto p-2 sm:p-3">
    <p v-if="error" class="inf-error">{{ error }}</p>
    <div v-if="cargando && !informe" class="inf-loading">Generando informe…</div>

    <template v-else-if="informe">
      <!-- ── Indicadores ── -->
      <div class="inf-kpis">
        <StatCard
          variant="pastel" tone="info" solid-icon
          label="Solicitudes totales" :value="informe.resumen.total"
          :comparacion="textoVariacion(informe.resumen.total, informe.anterior.total)"
          :icon="ClipboardIcon" :sparkline="serie('recibidas')"
        />
        <StatCard
          variant="pastel" tone="success" solid-icon
          label="Aceptadas" :value="informe.resumen.aceptadas"
          :comparacion="textoVariacion(informe.resumen.aceptadas, informe.anterior.aceptadas)"
          :icon="CheckIcon" :sparkline="serie('aceptadas')"
        />
        <StatCard
          variant="pastel" tone="danger" solid-icon
          label="Negadas" :value="informe.resumen.negadas"
          :comparacion="textoVariacion(informe.resumen.negadas, informe.anterior.negadas, 'menos-es-mejor')"
          :icon="XIcon" :sparkline="serie('negadas')"
        />
        <StatCard
          variant="pastel" tone="warning" solid-icon
          label="Canceladas" :value="informe.resumen.canceladas"
          :comparacion="textoVariacion(informe.resumen.canceladas, informe.anterior.canceladas, 'menos-es-mejor')"
          :icon="HourglassIcon" :sparkline="serie('canceladas')"
        />
      </div>

      <!-- ── Seguimiento diario ── -->
      <section class="inf-panel inf-seg">
        <header class="inf-head-panel" style="margin-bottom:8px">
          <span class="inf-panel-icon"><component :is="UsersIcon" class="w-3.5 h-3.5" /></span>
          <h2>Seguimiento diario</h2>
          <small class="inf-seg-hint">Control del estado de las referencias en el período seleccionado</small>
          <span class="inf-toolbar-label">Seguimiento:</span>
          <DateRangeFilter v-model="rangoSeg" />
        </header>
        <div class="inf-seg-row">
          <div class="inf-seg-left">
            <div class="inf-seg-total">
              <span>Pacientes referidos</span>
              <strong>{{ datosSeg.resumen.total }}</strong>
            </div>
            <div class="inf-seg-pob">
              <span><i class="inf-dot inf-dot-orange"></i>Población objeto <strong>{{ datosSeg.poblacion.objeto }}</strong> <em>{{ fmtPct(pctDe(datosSeg.poblacion.objeto, datosSeg.resumen.total)) }}</em></span>
              <span><i class="inf-dot inf-dot-blue"></i>Población no RED <strong>{{ datosSeg.poblacion.no_red }}</strong> <em>{{ fmtPct(pctDe(datosSeg.poblacion.no_red, datosSeg.resumen.total)) }}</em></span>
            </div>
          </div>
          <div class="inf-seg-cards">
            <div v-for="c in tarjetasSeguimiento" :key="c.label" class="inf-seg-card" :class="c.clase">
              <span class="inf-seg-icon"><component :is="c.icono" class="w-4 h-4" /></span>
              <div class="inf-seg-body">
                <span>{{ c.label }}</span>
                <strong>{{ c.valor }}</strong>
                <em>{{ c.pct }}</em>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Estados + Resumen ── -->
      <div class="inf-hero">
        <section class="inf-panel inf-t-indigo">
          <header class="inf-head-panel">
            <span class="inf-panel-icon"><component :is="PieIcon" class="w-3.5 h-3.5" /></span>
            <h2>Estado de las solicitudes</h2>
            <span class="inf-toolbar-label inf-head-space">Informe:</span>
            <DateRangeFilter v-model="rango" />
            <button type="button" class="inf-btn inf-btn-icon" :disabled="cargando" title="Actualizar" @click="cargar">
              <component :is="RefreshIcon" class="w-4 h-4" :class="{ 'animate-spin': cargando }" />
            </button>
          </header>
          <div class="inf-estados">
            <div v-if="informe.resumen.total > 0" class="inf-donut">
              <apexchart :key="'donut-' + (layout.isDarkMode ? 'd' : 'l')" type="donut" height="100%" width="100%" :options="opcionesDonut" :series="seriesDonut" />
            </div>
            <p v-else class="inf-empty inf-donut-empty">Sin solicitudes en el período.</p>
            <ul class="inf-legend">
              <li v-for="c in categorias" :key="c.key" :class="{ 'inf-legend-zero': c.total === 0 }">
                <span class="inf-legend-dot" :style="{ background: c.color }"></span>
                <span class="inf-legend-name">{{ c.label }}</span>
                <strong :style="{ color: c.color }">{{ c.total }}</strong>
                <small class="inf-legend-pct" :style="{ color: '#fff', background: c.color }">{{ fmtPct(c.pct) }}</small>
              </li>
            </ul>
          </div>
        </section>

        <section class="inf-panel inf-t-teal">
          <header class="inf-head-panel">
            <span class="inf-panel-icon"><component :is="LayersIcon" class="w-3.5 h-3.5" /></span>
            <h2>Resumen general</h2>
          </header>
          <ul class="inf-indicators">
            <li>
              <span class="inf-ind-icon inf-ind-blue"><component :is="ClipboardIcon" class="w-3.5 h-3.5" /></span>
              <span class="inf-ind-label">Pendiente por decidir</span>
              <span class="inf-ind-pill inf-ind-blue">{{ informe.resumen.nuevas }}</span>
            </li>
            <li>
              <span class="inf-ind-icon inf-ind-orange"><component :is="HourglassIcon" class="w-3.5 h-3.5" /></span>
              <span class="inf-ind-label">Pendiente por cupo</span>
              <span class="inf-ind-pill inf-ind-orange">{{ informe.resumen.pendientes_cupo }}</span>
            </li>
            <li>
              <span class="inf-ind-icon inf-ind-teal"><component :is="CheckIcon" class="w-3.5 h-3.5" /></span>
              <span class="inf-ind-label">Aceptadas por llegar</span>
              <span class="inf-ind-pill inf-ind-teal">{{ informe.resumen.aceptado }}</span>
            </li>
            <li>
              <span class="inf-ind-icon inf-ind-green"><component :is="BedIcon" class="w-3.5 h-3.5" /></span>
              <span class="inf-ind-label">Ingresos efectivos</span>
              <span class="inf-ind-pill inf-ind-green">{{ informe.resumen.ingresos_efectivos }}</span>
            </li>
            <li title="Pacientes que no ingresaron (aceptados que no llegaron) más las solicitudes canceladas">
              <span class="inf-ind-icon inf-ind-rose"><component :is="BanIcon" class="w-3.5 h-3.5" /></span>
              <span class="inf-ind-label">Canceladas</span>
              <span class="inf-ind-pill inf-ind-rose">{{ informe.resumen.negadas_no_ingreso + informe.resumen.canceladas }}</span>
            </li>
            <li>
              <span class="inf-ind-icon inf-ind-red"><component :is="XIcon" class="w-3.5 h-3.5" /></span>
              <span class="inf-ind-label">Negadas por decisión</span>
              <span class="inf-ind-pill inf-ind-red">{{ informe.resumen.negadas_decision }}</span>
            </li>
          </ul>
        </section>

        <section class="inf-panel inf-t-purple">
          <header class="inf-head-panel">
            <span class="inf-panel-icon"><component :is="BanIcon" class="w-3.5 h-3.5" /></span>
            <h2>Motivos de no ingreso</h2>
          </header>
          <div class="inf-motivos-dos">
            <div class="inf-mot-col inf-mot-azul">
              <div class="inf-mot-head"><span class="inf-mot-icon inf-mot-icon-azul"><component :is="UsersIcon" class="w-3 h-3" /></span>Población objeto</div>
              <ul v-if="motivosNoIngresoObjeto.length" class="inf-cat">
                <li v-for="f in motivosNoIngresoObjeto" :key="f.nombre">
                  <span class="inf-cat-name" :title="f.nombre">{{ f.nombre }}</span>
                  <span class="inf-bar"><span class="inf-bar-blue" :style="{ width: Math.max(f.pct, 2) + '%' }"></span></span>
                  <strong>{{ f.total }}</strong>
                  <span class="inf-cat-pct inf-cat-pill-blue">{{ fmtPct(f.pct) }}</span>
                </li>
              </ul>
              <p v-else class="inf-empty">Sin registros.</p>
            </div>
            <div class="inf-mot-col inf-mot-morado">
              <div class="inf-mot-head"><span class="inf-mot-icon inf-mot-icon-morado"><component :is="CrossIcon" class="w-3 h-3" /></span>Paciente no RED</div>
              <ul v-if="motivosNoIngresoNoRed.length" class="inf-cat">
                <li v-for="f in motivosNoIngresoNoRed" :key="f.nombre">
                  <span class="inf-cat-name" :title="f.nombre">{{ f.nombre }}</span>
                  <span class="inf-bar"><span class="inf-bar-purple" :style="{ width: Math.max(f.pct, 2) + '%' }"></span></span>
                  <strong>{{ f.total }}</strong>
                  <span class="inf-cat-pct inf-cat-pill-purple">{{ fmtPct(f.pct) }}</span>
                </li>
              </ul>
              <p v-else class="inf-empty">Sin registros.</p>
            </div>
          </div>
        </section>
      </div>


      <!-- ── Cuatro desgloses ── -->
      <div class="inf-grid inf-grid-4">
        <section v-for="col in cuatroColumnas" :key="col.titulo" class="inf-panel" :class="col.tema">
          <header class="inf-head-panel">
            <span class="inf-panel-icon" :class="col.iconoClase"><component :is="col.icono" class="w-3.5 h-3.5" /></span>
            <h2>{{ col.titulo }}</h2>
          </header>
          <ul v-if="col.filas.length" class="inf-cat">
            <li v-for="f in col.filas" :key="f.nombre" :class="{ 'inf-legend-zero': f.total === 0 }">
              <span class="inf-cat-name" :title="f.nombre">{{ f.nombre }}</span>
              <span class="inf-bar"><span :class="f.barra || col.barClass" :style="{ width: Math.max(f.pct, 2) + '%' }"></span></span>
              <strong>{{ f.total }}</strong>
              <span class="inf-cat-pct" :class="col.pillClass">{{ fmtPct(f.pct) }}</span>
            </li>
          </ul>
          <p v-else class="inf-empty">Sin registros en el período.</p>
        </section>
      </div>

      <!-- ── Especialidad que referencia ── -->
      <section class="inf-panel inf-t-indigo">
        <header class="inf-head-panel">
          <span class="inf-panel-icon"><component :is="StethoscopeIcon" class="w-3.5 h-3.5" /></span>
          <h2>Especialidad que referencia</h2>
        </header>
        <div class="inf-esp">
          <div v-for="e in especialidadesTop" :key="e.nombre" class="inf-esp-col">
            <span class="inf-esp-name" :title="e.nombre">{{ e.nombre }}</span>
            <strong>{{ e.total }} <small>({{ fmtPct(e.pct) }})</small></strong>
          </div>
        </div>
      </section>

    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch, type Component } from 'vue';
import {
  Ban as BanIcon,
  Building2 as BuildingIcon,
  Hourglass as HourglassIcon,
  MapPin as MapPinIcon,
  Mail as MailIcon,
  Stethoscope as StethoscopeIcon,
  Users as UsersIcon,
  BedDouble as BedIcon,
  CheckCircle2 as CheckIcon,
  ClipboardList as ClipboardIcon,
  Cross as CrossIcon,
  Layers as LayersIcon,
  PieChart as PieIcon,
  RefreshCw as RefreshIcon,
  ShieldCheck as ShieldIcon,
  UserX as UserXIcon,
  XCircle as XIcon,
} from '@lucide/vue';
import DateRangeFilter from '@/components/ui/DateRangeFilter.vue';
import StatCard from '@/components/ui/StatCard.vue';
import http from '@/plugins/axios';
import notify from '@/plugins/toast';
import { useLayoutStore } from '@/stores/layout';
import type { InformeDatos } from '@/lib/exportarInformePdf';

interface InformeCompleto extends InformeDatos {
  por_estado: (InformeDatos['por_estado'][number] & { estado: string })[];
  grupos_negacion: (InformeDatos['grupos_negacion'][number] & { grupo: string })[];
  por_dia: { fecha: string; recibidas: number; aceptadas: number; completadas: number; negadas: number; canceladas: number }[];
  por_municipio: { nombre: string; total: number; aceptado: number; completadas: number; pct: number }[];
  poblacion: { objeto: number; no_red: number };
  motivos_poblacion: { motivo: string; total: number; pct: number }[];
  motivos_no_red: { motivo: string; total: number; pct: number }[];
  no_red_por_eps: { nombre: string; total: number; pct: number }[];
  anterior: { total: number; aceptadas: number; completadas: number; negadas: number; canceladas: number; pendientes_cupo: number; tiempo_promedio_min: number | null };
}

/** Las cinco categorías de la dona "Estado de las solicitudes". */
const CATEGORIAS = [
  { key: 'aceptadas', label: 'Aceptadas', color: '#22c55e' },
  { key: 'negadas', label: 'Negadas', color: '#ef4444' },
  { key: 'pendientes_cupo', label: 'Pendiente por cupo', color: '#f97316' },
  { key: 'nuevas', label: 'Pendiente en espera', color: '#3b82f6' },
  { key: 'canceladas', label: 'Canceladas', color: '#94a3b8' },
] as const;

const layout = useLayoutStore();

// Sin rango seleccionado el informe cubre todo el historial.
const rango = ref<[string, string] | null>(null);

const informe = ref<InformeCompleto | null>(null);
const informeSeg = ref<InformeCompleto | null>(null);
const rangoSeg = ref<[string, string] | null>(null);
const cargandoSeg = ref(false);

const datosSeg = computed<InformeCompleto>(() => informeSeg.value ?? informe.value!);
const cargando = ref(false);
const error = ref('');

const parametros = (r: [string, string] | null = rango.value) => (r ? { desde: r[0], hasta: r[1] } : {});

async function cargarSeg(): Promise<void> {
  cargandoSeg.value = true;
  try {
    const { data } = await http.get<{ data: InformeCompleto }>('/api/informes', { params: parametros(rangoSeg.value) });
    informeSeg.value = data.data;
  } catch {
    notify.error('No se pudo actualizar el seguimiento.');
    informeSeg.value = informe.value;
  } finally {
    cargandoSeg.value = false;
  }
}

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = '';
  try {
    const { data } = await http.get('/api/informes', { params: parametros() });
    informe.value = data.data;
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'No se pudo generar el informe.';
  } finally {
    cargando.value = false;
  }
}

const fmtPct = (v: number): string => `${v.toLocaleString('es-CO')}%`;

/** Cambio porcentual contra el período anterior (si antes era 0 y ahora hay, cuenta como +100%). */
function variacion(actual: number, anterior: number): number {
  if (anterior === 0) return actual > 0 ? 100 : 0;
  return Math.round(((actual - anterior) / anterior) * 100);
}

/** "menos-es-mejor" invierte el sentido (negadas, tiempo): bajar es bueno, subir es malo. */
function textoVariacion(actual: number, anterior: number, sentido: 'mas-es-mejor' | 'menos-es-mejor' = 'mas-es-mejor'): string {
  const d = variacion(actual, anterior);
  if (d === 0) return '= sin cambios vs. período anterior';
  const mejora = sentido === 'mas-es-mejor' ? d > 0 : d < 0;
  return `${mejora ? '↑' : '↓'}${d > 0 ? '+' : ''}${d}% vs. período anterior`;
}

function porcentajeDelTotal(parte: number): number {
  const total = informe.value?.resumen.total ?? 0;
  return total > 0 ? Math.round((parte / total) * 1000) / 10 : 0;
}

const pctDe = (parte: number, total: number): number => (total > 0 ? Math.round((parte / total) * 1000) / 10 : 0);

function serie(clave: 'recibidas' | 'aceptadas' | 'completadas' | 'negadas' | 'canceladas'): number[] {
  const dias = informe.value?.por_dia ?? [];
  return dias.length > 1 ? dias.map((d) => d[clave]) : [0, 0, 0, 0, 0, 0, 0];
}

/** Las etiquetas "sin dato" del servidor se muestran como "Sin datos". */
function nombreCorto(nombre: string): string {
  if (['Sin EPS', 'SIN SEDE REGISTRADA', 'Sin especialidad'].includes(nombre)) return 'Sin datos';
  return nombre;
}

const cuatroColumnas = computed(() => informe.value
  ? [
      {
        titulo: 'Aceptadas por asegurador',
        tema: 'inf-t-green',
        icono: ShieldIcon, iconoClase: 'inf-icon-green', barClass: 'inf-bar-green', pillClass: 'inf-cat-pill-green',
        // Todas las aseguradoras con aceptadas en el período.
        filas: informe.value.detalle_aceptadas.por_eps.map((f) => ({ nombre: nombreCorto(f.nombre), total: f.total, pct: f.pct })),
      },
      {
        titulo: 'Aceptadas por servicio',
        tema: 'inf-t-teal',
        icono: LayersIcon, iconoClase: 'inf-icon-green', barClass: 'inf-bar-green', pillClass: 'inf-cat-pill-green',
        // Catálogo fijo de servicios de la clínica (se muestran aunque estén en cero).
        filas: informe.value.detalle_aceptadas.por_servicio.map((f) => ({ nombre: f.nombre, total: f.total, pct: f.pct })),
      },
      {
        titulo: 'Municipio de origen',
        tema: 'inf-t-orange',
        icono: MapPinIcon, iconoClase: 'inf-icon-blue', barClass: 'inf-bar-blue', pillClass: 'inf-cat-pill-blue',
        filas: informe.value.por_municipio.map((f) => ({ nombre: f.nombre, total: f.total, pct: pctDe(f.total, informe.value!.resumen.total) })),
      },
      {
        titulo: 'Clínica que referencia',
        tema: 'inf-t-blue',
        icono: BuildingIcon, iconoClase: 'inf-icon-blue', barClass: 'inf-bar-blue', pillClass: 'inf-cat-pill-blue',
        filas: informe.value.por_clinica.map((f) => ({ nombre: f.nombre, total: f.total, pct: pctDe(f.total, informe.value!.resumen.total) })),
      },
      {
        titulo: 'Origen de la solicitud',
        tema: 'inf-t-blue',
        icono: MailIcon, iconoClase: 'inf-icon-blue', barClass: 'inf-bar-blue', pillClass: 'inf-cat-pill-blue',
        filas: (informe.value.por_origen ?? []).map((f) => ({ nombre: f.nombre, total: f.total, pct: pctDe(f.total, informe.value!.resumen.total) })),
      },
    ]
  : []);

const especialidadesTop = computed(() => {
  const inf = informe.value;
  if (!inf) return [];
  const filas = [...inf.por_especialidad].sort((a, b) => b.total - a.total);
  const total = Math.max(1, inf.resumen.total);
  const top = filas.slice(0, 4).map((f) => ({ nombre: nombreCorto(f.nombre), total: f.total, pct: pctDe(f.total, total) }));
  const resto = filas.slice(4).reduce((a, f) => a + f.total, 0);
  top.push({ nombre: 'Otras especialidades', total: resto, pct: pctDe(resto, total) });
  return top;
});

/** Motivos de negación del grupo Población objeto que ocurrieron. */
const motivosNoIngresoObjeto = computed(() => (informe.value?.motivos_poblacion ?? [])
  .filter((m) => m.total > 0).map((m) => ({ nombre: m.motivo, total: m.total, pct: m.pct })));

/** EPS cuyas solicitudes fueron negadas como Paciente no RED. */
const motivosNoIngresoNoRed = computed(() => (informe.value?.no_red_por_eps ?? [])
  .filter((m) => m.total > 0).map((m) => ({ nombre: m.nombre, total: m.total, pct: m.pct })));

const tarjetasSeguimiento = computed(() => {
  const r = datosSeg.value?.resumen;
  if (!r) return [];
  const card = (label: string, valor: number, icono: Component, clase: string) => ({ label, valor, icono, clase, pct: fmtPct(pctDe(valor, r.total)) });
  return [
    card('Pendiente por aceptar', r.pendientes_cupo, HourglassIcon, 'seg-amber'),
    card('Pacientes aceptados', r.aceptadas, CheckIcon, 'seg-green'),
    card('Ingresos efectivos', r.ingresos_efectivos, BedIcon, 'seg-blue'),
    card('Ingresos no efectivos', r.negadas_no_ingreso, UserXIcon, 'seg-rose'),
    card('Pacientes negados', r.negadas, XIcon, 'seg-red'),
  ];
});

const categorias = computed(() => CATEGORIAS.map((c) => ({
  ...c,
  total: informe.value?.resumen[c.key] ?? 0,
  pct: porcentajeDelTotal(informe.value?.resumen[c.key] ?? 0),
})));

const categoriasConDatos = computed(() => categorias.value.filter((c) => c.total > 0));
const seriesDonut = computed(() => categoriasConDatos.value.map((c) => c.total));

const opcionesDonut = computed(() => ({
  chart: { background: 'transparent', fontFamily: 'inherit' },
  theme: { mode: layout.isDarkMode ? 'dark' : 'light' },
  labels: categoriasConDatos.value.map((c) => c.label),
  colors: categoriasConDatos.value.map((c) => c.color),
  legend: { show: false },
  dataLabels: { enabled: false },
  stroke: { width: 2, colors: [layout.isDarkMode ? '#161b28' : '#ffffff'] },
  plotOptions: {
    pie: {
      donut: {
        size: '72%',
        labels: {
          show: true,
          name: { show: true, fontSize: '11px', offsetY: 16 },
          value: { fontSize: '26px', fontWeight: 800, offsetY: -14, color: layout.isDarkMode ? '#e2ecfc' : '#0d2d5e' },
          total: { show: true, showAlways: true, label: 'Solicitudes', fontSize: '11px', color: '#94a3b8', formatter: () => String(informe.value?.resumen.total ?? 0) },
        },
      },
    },
  },
  tooltip: { y: { formatter: (v: number) => `${v} solicitudes` } },
}));

watch(rango, () => { void cargar(); });
watch(rangoSeg, () => { void cargarSeg(); });
onMounted(cargar);
</script>

<style scoped>
.inf-page { background: linear-gradient(160deg, #eef4fc 0%, #e3edf8 40%, #f0f5fa 100%); }
.dark .inf-page { background: transparent; }

/* ── Encabezado ── */
.inf-toolbar-label { color: #64748b; font-size: 11px; font-weight: 700; white-space: nowrap; }
.dark .inf-toolbar-label { color: #8fa4c4; }

.inf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 32px; padding: 5px 12px; border: 1.5px solid #dbe6f5; border-radius: 11px; background: rgba(255,255,255,.92); color: #315583; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(22,70,142,.07); transition: all .15s ease; }
.inf-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 5px 12px rgba(22,70,142,.12); }
.inf-btn:disabled { opacity: .5; cursor: not-allowed; }
.inf-btn-icon { width: 32px; padding: 6px; border-color: #bfd4f5; color: #2563eb; background: linear-gradient(135deg, #e4efff, #f4f8ff); }
.dark .inf-btn { border-color: #314666; background: #1c2d46; color: #bcd1ef; }

.inf-error { margin: 0 0 10px; padding: 10px 12px; border: 1px solid #fecaca; border-radius: 10px; background: #fef2f2; color: #b91c1c; font-size: 12px; }
.inf-loading { padding: 60px 0; color: #64748b; text-align: center; font-size: 13px; }

.inf-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; margin-bottom: 6px; flex-shrink: 0; }
@media (max-width: 1200px) { .inf-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 700px) { .inf-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

/* ── Seguimiento diario ── */
.inf-seg { margin-bottom: 6px; }
.inf-panel.inf-seg { z-index: 30; overflow: visible; }
.inf-hero > .inf-panel:first-child { z-index: 25; overflow: visible; }
.inf-seg-hint { color: #94a3b8; font-size: 10px; font-weight: 600; }
.inf-head-space { margin-left: auto; }
.inf-seg-row { display: grid; grid-template-columns: auto 1fr; gap: 14px; align-items: stretch; }
.inf-seg-left { display: flex; align-items: center; gap: 20px; padding: 10px 18px; border: 1px solid #c3d6f2; border-radius: 13px; background: linear-gradient(150deg, #f1f7ff, #f9fcff); }
.dark .inf-seg-left { border-color: #2c3f5e; background: #131a28; }
.inf-seg-total { display: flex; min-width: 96px; flex-direction: column; }
.inf-seg-total span { color: #64748b; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.dark .inf-seg-total span { color: #93a5c0; }
.inf-seg-total strong { font-size: 30px; font-weight: 800; line-height: 1.05; background: linear-gradient(135deg, #3b82f6, #4f46e5); -webkit-background-clip: text; background-clip: text; color: transparent; }
.dark .inf-seg-total strong { background: linear-gradient(135deg, #93c5fd, #a5b4fc); -webkit-background-clip: text; background-clip: text; color: transparent; }
.inf-seg-pob { display: flex; min-width: 150px; flex-direction: column; gap: 3px; font-size: 11px; }
.inf-seg-pob span { display: inline-flex; align-items: center; gap: 5px; color: #475569; font-weight: 600; }
.inf-seg-pob strong { font-weight: 800; }
.inf-seg-pob span:nth-of-type(1) strong { color: #ea580c; }
.inf-seg-pob span:nth-of-type(2) strong { color: #2563eb; }
.inf-seg-pob em { color: #94a3b8; font-style: normal; font-weight: 700; }
.dark .inf-seg-pob span { color: #cbd5e1; } .dark .inf-seg-pob strong { color: #e2ecfc; }
.inf-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
.inf-dot-orange { background: #fb923c; } .inf-dot-blue { background: #3b82f6; }
.inf-seg-cards { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 6px; }
.inf-seg-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 16px; border: 1px solid rgba(255,255,255,.35); box-shadow: 0 8px 24px rgba(22,70,142,.18); min-width: 0; transition: transform .15s ease, box-shadow .15s ease; }
.inf-seg-card:hover { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(22,70,142,.2); }
.dark .inf-seg-card:hover { transform: none; }
.dark .inf-seg-card { border-color: rgba(255,255,255,.18); }
.seg-amber { background: linear-gradient(135deg, #fcd34d, #f59e0b); box-shadow: 0 8px 22px rgba(245,158,11,.28); }
.seg-green { background: linear-gradient(135deg, #86efac, #22c55e); box-shadow: 0 8px 22px rgba(34,197,94,.28); }
.seg-blue { background: linear-gradient(135deg, #93c5fd, #3b82f6); box-shadow: 0 8px 22px rgba(59,130,246,.28); }
.seg-rose { background: linear-gradient(135deg, #fda4af, #f43f5e); box-shadow: 0 8px 22px rgba(244,63,94,.26); }
.seg-red { background: linear-gradient(135deg, #fca5a5, #ef4444); box-shadow: 0 8px 22px rgba(239,68,68,.28); }
.inf-seg-card .inf-seg-icon { background: rgba(255,255,255,.26); border: 1px solid rgba(255,255,255,.45); color: #fff; }
.inf-seg-card .inf-seg-body span { color: rgba(255,255,255,.88); }
.inf-seg-card .inf-seg-body em { color: rgba(255,255,255,.8); }
.inf-seg-card .inf-seg-body strong { color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.18); }
.inf-seg-icon { display: grid; width: 38px; height: 38px; flex-shrink: 0; place-items: center; border-radius: 12px; }
.inf-seg-body { display: flex; min-width: 0; flex-direction: column; }
.inf-seg-body span { overflow: hidden; color: #64748b; font-size: 10.5px; font-weight: 700; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
.dark .inf-seg-body span { color: #93a5c0; }
.inf-seg-body strong { color: #0d2d5e; font-size: 20px; font-weight: 800; line-height: 1.1; }
.dark .inf-seg-body strong { color: #e2ecfc; }
.dark .inf-seg-card .inf-seg-body strong { color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.18); }
.dark .inf-seg-card .inf-seg-body span { color: rgba(255,255,255,.88); }
.dark .inf-seg-card .inf-seg-body em { color: rgba(255,255,255,.8); }
.inf-seg-body em { margin-top: 1px; color: #64748b; font-size: 9.5px; font-style: normal; font-weight: 700; }
@media (max-width: 1400px) { .inf-seg { grid-template-columns: 1fr; } .inf-seg-cards { grid-template-columns: repeat(5, minmax(0,1fr)); } }
@media (max-width: 900px) { .inf-seg-cards { grid-template-columns: repeat(2, minmax(0,1fr)); } }

/* ── Especialidad que referencia ── */
.inf-esp { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 6px; }
.inf-esp-col { padding: 7px 10px; border: 1px solid #86a5d8; border-radius: 12px; background: rgba(248,251,255,.8); text-align: center; min-width: 0; transition: background .15s ease, transform .15s ease; }
.inf-esp-col:hover { background: #f0f6ff; transform: translateY(-1px); }
.dark .inf-esp-col:hover { background: #1e2940; transform: none; }
.dark .inf-esp-col { border-color: #263352; background: #1a2233; }
.inf-esp-name { display: block; overflow: hidden; color: #475569; font-size: 10.5px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.dark .inf-esp-name { color: #93a5c0; }
.inf-esp-col strong { color: #1d4ed8; font-size: 15px; font-weight: 800; }
.dark .inf-esp-col strong { color: #e2ecfc; }
.inf-esp-col strong small { color: #64748b; font-size: 9.5px; font-weight: 700; }
@media (max-width: 900px) { .inf-esp { grid-template-columns: repeat(2, minmax(0,1fr)); } }

/* ── Rejillas ── */
.inf-hero { display: grid; grid-template-columns: minmax(0,1.15fr) minmax(0,.85fr) minmax(0,1.55fr); gap: 6px; margin-bottom: 6px; align-items: stretch; }
.inf-hero .inf-panel { display: flex; flex-direction: column; }
.inf-hero .inf-panel > :last-child { flex: 1; min-height: 0; }
.inf-hero > .inf-panel { margin-bottom: 0; }
.inf-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; margin-bottom: 6px; align-items: stretch; }
.inf-grid > .inf-panel { margin-bottom: 0; }
.inf-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.inf-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
@media (max-width: 1200px) { .inf-hero { grid-template-columns: repeat(2, minmax(0,1fr)); } .inf-grid-4 { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 1000px) { .inf-grid { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .inf-hero, .inf-grid-4 { grid-template-columns: 1fr; } }

/* ── Paneles ── */
.inf-panel { position: relative; overflow: hidden; margin-bottom: 6px; padding: 10px 12px 9px; border: 1px solid #86a5d8; border-radius: 18px; background: #ffffff; box-shadow: 0 8px 24px rgba(22,70,142,.18), inset 0 1px 0 rgba(255,255,255,.9); min-width: 0; transition: box-shadow .2s ease; }
.inf-panel:hover { box-shadow: 0 12px 30px rgba(22,70,142,.22), inset 0 1px 0 rgba(255,255,255,.9); }
.dark .inf-panel { border-color: rgba(255,255,255,.07); background: #161b28; box-shadow: none; }
.dark .inf-panel:hover { box-shadow: none; }
.inf-head-panel { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px solid #edf2f9; }
.dark .inf-head-panel { border-color: #22304a; }
.inf-head-panel h2 { margin: 0; flex: 1; color: #0d2d5e; font-size: 12.5px; font-weight: 800; }
.dark .inf-head-panel h2 { color: #dbe7fb; }
.inf-panel-icon { display: grid; width: 22px; height: 22px; flex-shrink: 0; place-items: center; border-radius: 7px; background: linear-gradient(135deg, #2563eb, #1e40af); color: #fff; box-shadow: 0 4px 10px rgba(37,99,235,.3); }
.inf-icon-red { background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 4px 10px rgba(239,68,68,.3); }
.inf-icon-green { background: linear-gradient(135deg, #22c55e, #15803d); box-shadow: 0 4px 10px rgba(34,197,94,.3); }
.inf-icon-orange { background: linear-gradient(135deg, #fb923c, #ea580c); box-shadow: 0 4px 10px rgba(249,115,22,.3); }
.inf-icon-blue { background: linear-gradient(135deg, #60a5fa, #2563eb); box-shadow: 0 4px 10px rgba(59,130,246,.3); }

/* ── Temas de color por panel: solo el ícono del encabezado ── */
.inf-t-indigo .inf-panel-icon { background: linear-gradient(135deg, #818cf8, #4f46e5); box-shadow: 0 4px 10px rgba(99,102,241,.35); }
.inf-t-green .inf-panel-icon { background: linear-gradient(135deg, #4ade80, #16a34a); box-shadow: 0 4px 10px rgba(34,197,94,.3); }
.inf-t-teal .inf-panel-icon { background: linear-gradient(135deg, #2dd4bf, #0d9488); box-shadow: 0 4px 10px rgba(20,184,166,.3); }
.inf-t-purple .inf-panel-icon { background: linear-gradient(135deg, #a78bfa, #7c3aed); box-shadow: 0 4px 10px rgba(139,92,246,.3); }
.inf-t-orange .inf-panel-icon { background: linear-gradient(135deg, #fbbf24, #d97706); box-shadow: 0 4px 10px rgba(245,158,11,.3); }
.inf-t-blue .inf-panel-icon { background: linear-gradient(135deg, #60a5fa, #2563eb); box-shadow: 0 4px 10px rgba(59,130,246,.3); }
.inf-pill { padding: 2px 9px; border-radius: 999px; background: #e8f0fe; color: #1d4ed8; font-size: 10px; font-weight: 800; white-space: nowrap; }
.dark .inf-pill { background: #1d3454; color: #9dc1f7; }
.inf-empty { margin: 4px 0; color: #94a3b8; font-size: 12px; }


/* ── Estados: dona + leyenda lado a lado ── */
.inf-estados { display: flex; align-items: center; gap: 8px; }
.inf-donut { flex: 0 0 40%; height: 118px; }
.inf-donut-empty { flex: 0 0 40%; text-align: center; }
.inf-legend { display: flex; flex: 1; flex-direction: column; gap: 4px; min-width: 0; margin: 0; padding: 0; list-style: none; align-self: stretch; justify-content: space-between; }
.inf-legend li { display: flex; align-items: center; gap: 6px; font-size: 10.5px; padding: 1px 0 3px; border-bottom: 1px solid #f1f5f9; }
.inf-legend li:last-child { border-bottom: 0; padding-bottom: 1px; }
.dark .inf-legend li { border-color: #22304a; }
.inf-legend-zero { opacity: .5; }
.inf-legend-dot { width: 8px; height: 8px; flex-shrink: 0; border-radius: 50%; }
.inf-legend-name { flex: 1; min-width: 0; overflow: hidden; color: #475569; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.inf-legend strong { color: #0d2d5e; font-weight: 800; }
.inf-legend small { min-width: 40px; color: #94a3b8; font-weight: 700; text-align: right; }
.inf-legend-pct { padding: 1px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 800; }
.dark .inf-legend-name { color: #b6c2d6; }
.dark .inf-legend strong { color: #e2ecfc; }
@media (max-width: 600px) { .inf-estados { flex-direction: column; align-items: stretch; } .inf-donut, .inf-donut-empty { flex-basis: auto; } }

/* ── Resumen general ── */
.inf-indicators { display: flex; flex: 1; flex-direction: column; gap: 5px; margin: 0; padding: 0; list-style: none; }
.inf-indicators li { display: flex; flex: 1; align-items: center; gap: 8px; padding: 5px 9px; border: 1px solid #86a5d8; border-radius: 12px; background: rgba(248,251,255,.8); transition: background .15s ease; }
.inf-indicators li:hover { background: #f0f6ff; }
.dark .inf-indicators li:hover { background: #1e2940; }
.inf-ind-icon { display: grid; width: 22px; height: 22px; flex-shrink: 0; place-items: center; border-radius: 8px; }
.inf-ind-label { flex: 1; color: #334155; font-size: 11px; font-weight: 600; }
.inf-ind-pill { min-width: 32px; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; text-align: center; }
.inf-ind-green { background: #16a34a; color: #fff; }
.inf-ind-red { background: #dc2626; color: #fff; }
.inf-ind-rose { background: #e11d48; color: #fff; }
.inf-ind-orange { background: #ea580c; color: #fff; }
.inf-ind-blue { background: #2563eb; color: #fff; }
.inf-ind-teal { background: #0d9488; color: #fff; }
.inf-ind-gray { background: #64748b; color: #fff; }
.dark .inf-indicators li { border-color: #263352; background: #1a2233; }
.dark .inf-ind-label { color: #cbd5e1; }
.dark .inf-ind-green { background: #16a34a; color: #fff; }
.dark .inf-ind-red { background: #dc2626; color: #fff; }
.dark .inf-ind-rose { background: #e11d48; color: #fff; }
.dark .inf-ind-orange { background: #ea580c; color: #fff; }
.dark .inf-ind-blue { background: #2563eb; color: #fff; }
.dark .inf-ind-teal { background: #0d9488; color: #fff; }
.dark .inf-ind-gray { background: #64748b; color: #fff; }

/* ── Listas de categorías (4 desgloses) ── */
.inf-cat { display: flex; flex-direction: column; gap: 5px; margin: 0; padding: 0 2px 0 0; list-style: none; max-height: 176px; overflow-x: hidden; overflow-y: auto; scrollbar-width: thin; scrollbar-color: transparent transparent; }
.inf-cat:hover { scrollbar-color: rgba(96,140,200,.55) transparent; }
.dark .inf-cat:hover { scrollbar-color: rgba(148,163,184,.4) transparent; }
.inf-cat::-webkit-scrollbar { width: 5px; }
.inf-cat::-webkit-scrollbar-track { background: transparent; }
.inf-cat::-webkit-scrollbar-thumb { background: transparent; border-radius: 99px; }
.inf-cat:hover::-webkit-scrollbar-thumb { background: rgba(96,140,200,.55); }
.dark .inf-cat:hover::-webkit-scrollbar-thumb { background: rgba(148,163,184,.4); }
.inf-cat li { display: flex; align-items: center; gap: 6px; padding: 2px 4px; margin: 0 -4px; border-radius: 6px; transition: background .15s ease; }
.inf-cat li:hover { background: rgba(59,130,246,.08); }
.dark .inf-cat li:hover { background: rgba(148,163,184,.08); }
.inf-cat-name { flex: 0 0 104px; overflow: hidden; color: #334155; font-size: 10.5px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.dark .inf-cat-name { color: #cbd5e1; }
.inf-cat .inf-bar { flex: 1; height: 6px; }
.inf-cat strong { min-width: 16px; color: #0d2d5e; font-size: 11px; font-weight: 800; text-align: right; }
.dark .inf-cat strong { color: #e2ecfc; }
.inf-cat-pct { flex: 0 0 42px; padding: 1px 0; border-radius: 999px; font-size: 9.5px; font-weight: 800; text-align: center; }
.inf-cat-pill-green { background: #16a34a; color: #fff; }
.inf-cat-pill-red { background: #dc2626; color: #fff; }
.inf-cat-pill-orange { background: #ea580c; color: #fff; }
.inf-cat-pill-blue { background: #2563eb; color: #fff; }
.inf-motivos-dos { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 8px; flex: 1; min-height: 0; }
.inf-mot-col { border: 1px solid #c3d6f2; border-radius: 10px; padding: 8px 9px; background: #f8fbff; display: flex; flex-direction: column; min-height: 0; }
.inf-mot-azul { border-color: #c3d6f2; background: linear-gradient(180deg, #f2f8ff, #fafcff); }
.inf-mot-morado { border-color: #ddd4f7; background: linear-gradient(180deg, #f6f3ff, #fbfaff); }
.dark .inf-mot-col { border-color: #2c3f5e; background: #131a28; }
.inf-mot-head { display: flex; align-items: center; gap: 6px; margin-bottom: 7px; padding-bottom: 6px; border-bottom: 1px dashed #d5e1f4; font-size: 11px; font-weight: 800; color: #315583; }
.dark .inf-mot-head { color: #9db8e0; border-color: #2c3f5e; }
.inf-mot-icon { width: 20px; height: 20px; border-radius: 6px; display: grid; place-items: center; color: #fff; flex: none; }
.inf-mot-icon-azul { background: linear-gradient(135deg, #60a5fa, #2563eb); box-shadow: 0 3px 8px rgba(59,130,246,.3); }
.inf-mot-icon-morado { background: linear-gradient(135deg, #a78bfa, #7c3aed); box-shadow: 0 3px 8px rgba(139,92,246,.3); }
.inf-mot-col .inf-cat { flex: 1; justify-content: space-evenly; overflow-y: auto; }
.inf-bar-purple { background: linear-gradient(90deg, #a78bfa, #6d28d9); }
.inf-cat-pill-purple { background: #7c3aed; color: #fff; }

.inf-sub-grupo { display: flex; align-items: center; gap: 6px; margin: 4px 0 5px; color: #315583; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; }
.inf-sub-grupo:first-of-type { margin-top: 0; }
.dark .inf-sub-grupo { color: #9db8e0; }
.dark .inf-cat-pill-green { background: #16a34a; color: #fff; }
.dark .inf-cat-pill-red { background: #dc2626; color: #fff; }
.dark .inf-cat-pill-orange { background: #ea580c; color: #fff; }
.dark .inf-cat-pill-blue { background: #2563eb; color: #fff; }
.dark .inf-cat-pill-purple { background: #7c3aed; color: #fff; }
@media (max-width: 1400px) { .inf-cat-name { flex-basis: 92px; } }

.inf-bar { display: block; height: 7px; overflow: hidden; border-radius: 99px; background: #eef2f7; }
.inf-bar-thin { height: 6px; }
.inf-bar > span { display: block; height: 100%; border-radius: 99px; }
.inf-bar-red { background: linear-gradient(90deg, #f87171, #b91c1c); }
.inf-bar-orange { background: linear-gradient(90deg, #fb923c, #c2410c); }
.inf-bar-green { background: linear-gradient(90deg, #22c55e, #15803d); }
.inf-bar-blue { background: linear-gradient(90deg, #60a5fa, #1d4ed8); }
.dark .inf-bar { background: #263352; }







</style>
