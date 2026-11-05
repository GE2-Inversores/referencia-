<template>
  <div class="h-full flex flex-col gap-2.5 p-2 sm:p-3 overflow-y-auto lg:overflow-hidden dashboard-bg">

    <!-- ── Aviso de error ── -->
    <div v-if="errorCarga" class="dash-error-banner shrink-0">
      <component :is="AlertTriangleIcon" class="w-4 h-4" />
      <span>No se pudieron cargar las estadísticas. Verifica tu conexión.</span>
      <button @click="cargarStats">Reintentar</button>
    </div>

    <!-- ── Stat cards ── -->
    <div v-if="primeraCarga && cargando" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 shrink-0">
      <div v-for="i in 4" :key="i" class="stat-card-pastel-skeleton rounded-2xl p-3.5 flex items-center gap-3">
        <div class="shimmer-box" style="width:42px; height:42px; border-radius:12px; flex-shrink:0;"></div>
        <div class="flex-1 space-y-2">
          <div class="shimmer-bar" style="width:50%; height:20px;"></div>
          <div class="shimmer-bar" style="width:65%; height:10px;"></div>
        </div>
      </div>
    </div>
    <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 shrink-0 animate-fade-in-up"
      style="animation-duration: 0.4s; animation-delay: 0.1s; animation-fill-mode: both;">
      <StatCard
        v-for="(card, i) in statCards" :key="i"
        class="anim-slide-up"
        :style="{ animationDelay: (i * 0.06) + 's' }"
        variant="pastel"
        :dark="isDark"
        :tone="card.tone"
        :value="card.value"
        :label="card.label"
        :icon="card.icon"
        :percent="card.percent"
        :delta="card.delta"
        :sparkline="card.sparkline"
        :comparacion="card.comparacion"
      />
    </div>

    <!-- ── Solicitudes por día + Distribución + Actividad ── -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 lg:flex-1 lg:min-h-0 animate-fade-in-up"
      style="animation-duration: 0.4s; animation-delay: 0.18s; animation-fill-mode: both;">

      <!-- Tendencia -->
      <div class="panel-card-ui p-3.5 flex flex-col lg:col-span-6 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="BarChart3Icon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Tendencia de solicitudes</p>
          <PanelHelp texto="Cuántas solicitudes llegan a lo largo del tiempo. Cambia entre día, semana, mes o año; la línea tenue compara con el período anterior." />
          <div class="chart-legend">
            <span class="chart-legend-pill chart-legend-pill--actual">
              <span class="chart-legend-dot" style="background:#8fb5f5"></span>Actual
            </span>
          </div>
          <PeriodoSeg v-model="granularidad" v-model:fecha="fechaTendencia" sin-todo />
        </div>
        <div class="flex-1 min-h-0">
          <apexchart :key="'tend-' + granularidad + (isDark ? 'd' : 'l')" type="line" height="100%" :options="diaChartOptions" :series="diaChartSeries" />
        </div>
      </div>

      <!-- Dona de estados -->
      <div class="panel-card-ui p-3.5 flex flex-col lg:col-span-3 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="PieChartIcon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Distribución por estado</p>
          <PanelHelp texto="Cómo están repartidas las solicitudes según su estado actual. Pasa el mouse sobre cada color para ver el detalle; los botones cambian el período." />
          <PeriodoSeg v-model="periodos.donut.p" v-model:fecha="periodos.donut.ref" />
        </div>
        <div class="flex-1 flex items-center min-h-0 overflow-hidden gap-2">
          <div class="estado-donut-wrap">
            <apexchart v-if="stats.distribucion.total > 0" :key="'donut-' + (isDark ? 'd' : 'l')" type="donut" height="100%" width="100%" :options="estadoDonutOptions" :series="estadoDonutSeries" />
            <div v-else class="estado-donut-empty">Sin solicitudes<br>en este período</div>
            <div v-if="stats.distribucion.total > 0" class="estado-donut-center">
              <template v-if="donutHover">
                <span class="estado-donut-label" :style="{ color: donutHover.color }">{{ donutHover.label }}</span>
                <span class="estado-donut-num">{{ donutHover.value }}</span>
                <span class="estado-donut-label">{{ donutHover.pct }}% del total</span>
              </template>
              <template v-else>
                <span class="estado-donut-num">{{ stats.distribucion.total }}</span>
                <span class="estado-donut-label">Solicitudes</span>
              </template>
            </div>
          </div>
          <!-- Leyenda lateral -->
          <div class="estado-legend">
            <div v-for="b in donutBars" :key="b.label" class="estado-legend-row">
              <div class="estado-legend-top">
                <span class="estado-legend-dot" :style="{ background: b.color }"></span>
                <span class="estado-legend-name">{{ b.label }}</span>
                <span class="estado-legend-val">{{ b.value }}</span>
                <span class="estado-legend-pct">{{ b.pct }}%</span>
              </div>
              <div class="estado-legend-bar">
                <div class="estado-legend-fill" :style="{ width: b.pct + '%', background: b.color }"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Actividad reciente -->
      <div class="panel-card-ui p-3.5 flex flex-col lg:col-span-3 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="ActivityIcon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Actividad reciente</p>
          <PanelHelp alinear="derecha" texto="Los últimos movimientos del sistema: solicitudes recibidas, aceptadas, negadas o puestas en espera." />
          <router-link to="/historico" class="panel-link-sm">Ver todo</router-link>
        </div>
        <div class="flex-1 w-full flex flex-col gap-1 min-h-0 overflow-y-auto activity-list">
          <div v-for="(ev, i) in stats.actividad" :key="i" class="activity-row" :class="{ 'activity-row--last': i === stats.actividad.length - 1 }">
            <span class="activity-icon" :style="{ background: actividadTono(ev.tipo).bg, color: actividadTono(ev.tipo).color }">
              <component :is="actividadTono(ev.tipo).icon" class="w-3.5 h-3.5" />
            </span>
            <div class="flex-1 min-w-0">
              <p class="activity-title">{{ ev.titulo }}</p>
              <p class="activity-detail" :title="ev.detalle">{{ ev.detalle }}</p>
            </div>
            <span class="activity-time">{{ haceTexto(ev.at) }}</span>
          </div>
          <div v-if="!stats.actividad.length" class="panel-empty">
            <component :is="ActivityIcon" class="w-6 h-6" />
            <span>Sin actividad reciente</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Especialidades + SLA + Top clínicas ── -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 lg:flex-[1.15] lg:min-h-0 animate-fade-in-up"
      style="animation-duration: 0.4s; animation-delay: 0.25s; animation-fill-mode: both;">

      <!-- Especialidades (tabla) -->
      <div class="panel-card-ui p-3.5 flex flex-col lg:col-span-4 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="StethoscopeIcon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Solicitudes por especialidad</p>
          <PanelHelp texto="Las especialidades más solicitadas, qué porcentaje representan del total y su tendencia reciente. Los botones cambian el período." />
          <PeriodoSeg v-model="periodos.especialidades.p" v-model:fecha="periodos.especialidades.ref" />
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto esp-scroll">
          <table class="dash-table dash-table--esp">
            <thead>
              <tr>
                <th>Especialidad</th>
                <th class="text-right">Solicitudes</th>
                <th class="text-right">%</th>
                <th class="text-right">Tendencia</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in especialidadRows" :key="item.nombre">
                <td>
                  <div class="flex items-center gap-2">
                    <span class="esp-icon" :style="{ background: item.tint, color: item.color }">
                      <component :is="item.icon" class="w-3.5 h-3.5" />
                    </span>
                    <div class="flex-1 min-w-0">
                      <p class="tbl-name" :title="item.nombre">{{ item.nombre }}</p>
                      <div class="tbl-bar-track">
                        <div class="tbl-bar-fill" :style="{ width: item.barPct + '%', background: `linear-gradient(90deg, ${item.color}, ${item.spark})` }"></div>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="text-right tbl-num">{{ item.total }}</td>
                <td class="text-right tbl-sub">{{ item.pct }}%</td>
                <td class="text-right">
                  <Sparkline :valores="item.serie" :color="item.spark" :ancho="52" :alto="18" class="inline-block" />
                </td>
              </tr>
              <tr v-if="!especialidadRows.length">
                <td colspan="4" class="tbl-empty">Sin datos de especialidades</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- SLA -->
      <div class="panel-card-ui p-3.5 flex flex-col lg:col-span-5 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="TimerIcon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Tiempo de respuesta (SLA)</p>
          <PanelHelp texto="Qué tan rápido responde el equipo: cuántas solicitudes se contestaron en menos de 2h, entre 2–4h o más de 4h; la línea es el tiempo promedio. Los botones cambian el período (día agrupa por horas, año por meses)." />
          <PeriodoSeg v-model="periodos.sla.p" v-model:fecha="periodos.sla.ref" />
        </div>
        <div class="flex-1 min-h-0 flex flex-col">
          <!-- Leyenda -->
          <div class="sla-legend">
            <span class="sla-legend-item"><i style="background:#22c55e"></i>≤ 2 horas</span>
            <span class="sla-legend-item"><i style="background:#f59e0b"></i>2 – 4 horas</span>
            <span class="sla-legend-item"><i style="background:#ef4444"></i>&gt; 4 horas</span>
            <span class="sla-legend-item"><i class="sla-legend-line"></i>Tiempo promedio</span>
          </div>
          <!-- Plot: barras apiladas + línea de promedio (SVG propio) -->
          <div class="sla-plot">
            <div v-for="tick in slaTicks" :key="tick" class="sla-gridline" :style="{ bottom: tick.pct + '%' }">
              <span class="sla-gridlabel">{{ tick.v }}</span>
            </div>
            <div class="sla-bars">
              <div
                v-for="(d, i) in stats.sla_diario.dias" :key="d.fecha"
                class="sla-col"
                @mouseenter="slaHoverShow($event, i)" @mouseleave="hideTip"
              >
                <div class="sla-seg" :style="{ height: slaSegH(d.mas4) + '%', background: 'linear-gradient(180deg,#f87171,#dc2626)' }"></div>
                <div class="sla-seg" :style="{ height: slaSegH(d.de2a4) + '%', background: 'linear-gradient(180deg,#fbbf24,#d97706)' }"></div>
                <div class="sla-seg" :style="{ height: slaSegH(d.hasta2) + '%', background: 'linear-gradient(180deg,#4ade80,#16a34a)' }"></div>
              </div>
            </div>
            <svg class="sla-svg" viewBox="0 0 100 100" preserveAspectRatio="none">
              <path :d="slaLinePath" fill="none" :stroke="isDark ? '#93c5fd' : '#1e3a5f'" vector-effect="non-scaling-stroke" class="sla-line-path" />
            </svg>
            <span
              v-for="(p, i) in slaLinePoints" :key="'dot' + i"
              class="sla-dot"
              :style="{ left: p.x + '%', top: p.y + '%' }"
            ></span>
          </div>
          <!-- Etiquetas X -->
          <div class="sla-xaxis">
            <span v-for="d in stats.sla_diario.dias" :key="d.fecha" class="sla-xlabel">{{ d.etiqueta }}</span>
          </div>
        </div>
        <div class="sla-summary">
          <div v-for="(banda, i) in stats.sla.bandas" :key="banda.etiqueta" class="sla-summary-item">
            <span class="sla-summary-pct" :style="{ color: slaColors[i] }">{{ banda.pct }}%</span>
            <span class="sla-summary-label">{{ banda.etiqueta }}</span>
            <span class="sla-summary-count">{{ banda.total }} solicitudes</span>
          </div>
          <div class="sla-summary-item sla-summary-item--prom">
            <span class="sla-summary-pct" style="color:#1e293b;">{{ stats.sla_diario.promedio || '—' }}</span>
            <span class="sla-summary-label">Tiempo promedio</span>
            <span v-if="stats.sla_diario.promedio_delta !== null" class="sla-summary-delta"
              :class="stats.sla_diario.promedio_delta <= 0 ? 'sla-summary-delta--ok' : 'sla-summary-delta--bad'">
              {{ stats.sla_diario.promedio_delta <= 0 ? '↓' : '↑' }}{{ Math.abs(stats.sla_diario.promedio_delta) }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Top clínicas (tabla) -->
      <div class="panel-card-ui p-3.5 flex flex-col lg:col-span-3 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="HospitalIcon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Top clínicas emisoras</p>
          <PanelHelp alinear="derecha" texto="Las clínicas que más solicitudes envían, su participación sobre el total y su tendencia reciente." />
        </div>
        <div class="flex-1 min-h-0 overflow-hidden">
          <table class="dash-table">
            <thead>
              <tr>
                <th style="width:24px;">#</th>
                <th>Clínica</th>
                <th class="text-right">Sol.</th>
                <th class="text-right">%</th>
                <th class="text-right">Tend.</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in stats.top_clinicas" :key="item.nombre">
                <td><span class="top-rank" :class="`top-rank-${i + 1}`">{{ i + 1 }}</span></td>
                <td><p class="tbl-name" :title="item.nombre">{{ item.nombre }}</p></td>
                <td class="text-right tbl-num">{{ item.total }}</td>
                <td class="text-right tbl-sub">{{ item.pct }}%</td>
                <td class="text-right">
                  <Sparkline :valores="item.serie" :color="CLI_SPARK[i % CLI_SPARK.length]" :ancho="40" :alto="16" class="inline-block" />
                </td>
              </tr>
              <tr v-if="!stats.top_clinicas.length">
                <td colspan="5" class="tbl-empty">Sin datos de clínicas</td>
              </tr>
            </tbody>
          </table>
        </div>
        <router-link to="/clinicas" class="panel-link">
          Ver todas las clínicas
          <component :is="ArrowRightIcon" class="w-3.5 h-3.5" />
        </router-link>
      </div>
    </div>

    <!-- ── Solicitudes recientes + Mapa de calor ── -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2.5 lg:flex-1 lg:min-h-0 animate-fade-in-up"
      style="animation-duration: 0.4s; animation-delay: 0.32s; animation-fill-mode: both;">

      <!-- Recientes -->
      <div class="panel-card-ui p-3 flex flex-col lg:col-span-6 min-h-0">
      <div class="panel-head">
        <div class="panel-head-icon">
          <component :is="ClipboardListIcon" class="w-3.5 h-3.5" />
        </div>
        <p class="panel-head-title">Solicitudes recientes</p>
        <PanelHelp texto="Las últimas solicitudes recibidas con su estado actual." />
        <router-link to="/solicitudes-referencia" class="panel-link-sm ml-2">Ver todas</router-link>
      </div>
      <div class="flex-1 min-h-0 overflow-auto recientes-scroll">
        <table class="dash-table dash-table--recientes dash-table--compact">
          <thead>
            <tr>
              <th style="width:28px;">#</th>
              <th>Paciente</th>
              <th>Clínica emisora</th>
              <th>Especialidad</th>
              <th>Estado</th>
              <th>Fecha solicitud</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, i) in stats.solicitudes_recientes" :key="s.id">
              <td class="tbl-sub">{{ i + 1 }}</td>
              <td>
                <div class="flex items-center gap-2">
                  <span class="pac-avatar" :style="{ background: pacAvatarColor(s.paciente).bg, color: pacAvatarColor(s.paciente).color }">
                    {{ pacIniciales(s.paciente) }}
                  </span>
                  <div class="min-w-0">
                    <p class="tbl-name">{{ s.paciente }}</p>
                    <p class="tbl-sub">{{ s.tipo_documento }} {{ s.numero_documento }}</p>
                  </div>
                </div>
              </td>
              <td><p class="tbl-cell tbl-ellipsis" :title="s.clinica">{{ s.clinica || '—' }}</p></td>
              <td><p class="tbl-name tbl-name--esp" :title="s.especialidad">{{ s.especialidad }}</p></td>
              <td><span class="estado-badge" :class="`estado-badge--${s.estado}`">{{ estadoLabel(s.estado) }}</span></td>
              <td class="tbl-sub whitespace-nowrap">{{ s.fecha_solicitud }}</td>
              <td class="text-right whitespace-nowrap">
                <button class="tbl-action" title="Ver solicitud" @click="verSolicitud(s.id)">
                  <component :is="EyeIcon" class="w-3.5 h-3.5" />
                </button>
                <button class="tbl-action" title="Descargar PDF" :disabled="pdfCargando === s.id" @click="descargarPdf(s.id)">
                  <component :is="FileDownIcon" class="w-3.5 h-3.5" />
                </button>
                <button class="tbl-action" title="Abrir en solicitudes" @click="verSolicitud(s.id)">
                  <component :is="MoreIcon" class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
            <tr v-if="!stats.solicitudes_recientes.length">
              <td colspan="7" class="tbl-empty">Sin solicitudes recientes</td>
            </tr>
          </tbody>
        </table>
      </div>
      </div>

      <!-- Mapa de calor: día × hora -->
      <div class="panel-card-ui p-3 flex flex-col lg:col-span-3 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="CalClockIcon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Solicitudes por día y hora</p>
          <PanelHelp texto="En qué momentos llegan más solicitudes — las celdas más oscuras son los días y horas de mayor volumen. Los botones cambian el período." />
          <PeriodoSeg v-model="periodos.diahora.p" v-model:fecha="periodos.diahora.ref" />
        </div>
        <div class="flex-1 min-h-0 flex flex-col">
          <div class="heatmap-grid flex-1 min-h-0">
            <span></span>
            <span v-for="h in heatHoras" :key="'h' + h" class="heatmap-axis">{{ h }}</span>
            <template v-for="dia in heatDias" :key="dia">
              <span class="heatmap-axis heatmap-axis--dia">{{ dia }}</span>
              <span
                v-for="h in heatHoras" :key="dia + h"
                class="heatmap-cell"
                :style="{ background: heatColor(dia, h) }"
                @mouseenter="heatTip($event, dia, h)"
                @mouseleave="hideTip"
              ></span>
            </template>
          </div>
        </div>
        <div class="heatmap-legend">
          <span>Menos</span>
          <i v-for="s in 5" :key="s" class="heatmap-cell" :style="{ background: heatScale((s - 1) / 4) }"></i>
          <span>Más</span>
        </div>
      </div>

      <!-- Mapa de calor: especialidad × mes -->
      <div class="panel-card-ui p-3 flex flex-col lg:col-span-3 min-h-0">
        <div class="panel-head">
          <div class="panel-head-icon">
            <component :is="GridIcon" class="w-3.5 h-3.5" />
          </div>
          <p class="panel-head-title">Mapa de calor</p>
          <PanelHelp alinear="derecha" texto="Las especialidades más pedidas mes a mes — sirve para ver qué servicios crecen o bajan en el tiempo. Los botones cambian el período." />
          <PeriodoSeg v-model="periodos.heatmap.p" v-model:fecha="periodos.heatmap.ref" />
        </div>
        <div v-if="espMesSeries.length" class="flex-1 min-h-0">
          <apexchart :key="'espmes-' + (isDark ? 'd' : 'l')" type="heatmap" height="100%" :options="espMesOptions" :series="espMesSeries" />
        </div>
        <div v-else class="flex-1 min-h-0 flex items-center justify-center text-[10px] text-slate-400">
          Sin datos de especialidades
        </div>
      </div>

    </div>

    <!-- Tooltip del SLA: teleportado a body para que flote por encima de
         cualquier contenedor con overflow:hidden (el panel lo recortaba). -->
    <Teleport to="body">
      <div
        v-if="floatingTip"
        class="sla-tip"
        :style="{ left: floatingTip.x + 'px', top: floatingTip.y + 'px' }"
      >
        <p class="sla-tip-fecha">{{ floatingTip.title }}</p>
        <p v-for="(r, i) in floatingTip.rows" :key="i" class="sla-tip-row">
          <i v-if="r.line" class="sla-legend-line"></i>
          <i v-else :style="{ background: r.color }"></i>
          {{ r.label }} <b>{{ r.value }}</b>
        </p>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, reactive, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useLayoutStore } from '@/stores/layout';
import { useAuthStore } from '@/stores/auth';
import { usePolling } from '@/lib/usePolling';
import { exportarSolicitudPdf } from '@/lib/exportarSolicitudPdf';
import {
  ArrowRight as ArrowRightIcon,
  Clock3 as ClockIcon,
  ClipboardList as ClipboardListIcon,
  Hospital as HospitalIcon,
  PieChart as PieChartIcon,
  BarChart3 as BarChart3Icon,
  Stethoscope as StethoscopeIcon,
  Timer as TimerIcon,
  Activity as ActivityIcon,
  FilePlus2 as FilePlusIcon,
  CheckCircle2 as CheckCircleIcon,
  XCircle as XCircleIcon,
  UserPlus as UserPlusIcon,
  Heart as HeartIcon,
  Sparkles as SparklesIcon,
  Bone as BoneIcon,
  Brain as BrainIcon,
  Eye as EyeIcon,
  FileDown as FileDownIcon,
  CalendarClock as CalClockIcon,
  Grid3x3 as GridIcon,
  MoreHorizontal as MoreIcon,
  X as XIcon,
  AlertTriangle as AlertTriangleIcon,
} from '@lucide/vue';
import http from '@/plugins/axios';
import StatCard, { type StatCardTone } from '@/components/ui/StatCard.vue';
import DateRangeFilter from '@/components/ui/DateRangeFilter.vue';
import Sparkline from '@/components/ui/Sparkline.vue';
import PanelHelp from '@/components/ui/PanelHelp.vue';
import PeriodoSeg from '@/components/ui/PeriodoSeg.vue';

const router = useRouter();
const layout = useLayoutStore();
const auth = useAuthStore();
const isDark = computed(() => layout.isDarkMode);

const nombreSaludo = computed(() => auth.user?.first_name || auth.user?.full_name?.split(' ')[0] || 'usuario');

const cargando = ref(true);
const primeraCarga = ref(true);
const errorCarga = ref(false);
const pdfCargando = ref<number | null>(null);

type ActividadEvento = { tipo: string; titulo: string; detalle: string; at: string | null };
type TopConSerie = { nombre: string; total: number; pct: number; serie: number[] };
type SlaDiario = {
  dias: Array<{ fecha: string; etiqueta: string; hasta2: number; de2a4: number; mas4: number; avg_horas: number }>;
  promedio: string | null;
  promedio_min: number | null;
  promedio_delta: number | null;
};

type Periodo = 'dia' | 'semana' | 'mes' | 'anio' | 'todo';

const stats = ref({
  solicitudes: { total: 0, pendientes: 0, negadas: 0, en_espera: 0, completadas: 0 },
  distribucion: { total: 0, pendientes: 0, negadas: 0, en_espera: 0, completadas: 0, canceladas: 0 },
  clinicas: { total: 0, activas: 0, pendientes: 0, nuevas_mes: 0 },
  usuarios: { total: 0, activos: 0 },
  solicitudes_recientes: [] as any[],
  filtros: { especialidades: [] as string[], eps: [] as string[], clinicas: [] as Array<{ id: number; nombre: string }> },
  tendencia: [] as Array<{ label: string; total: number }>,
  tendencia_anterior: [] as Array<{ label: string; total: number }>,
  tendencia_clinicas: [] as Array<{ fecha: string; total: number }>,
  tendencia_usuarios: [] as Array<{ fecha: string; total: number }>,
  tendencia_pendientes: [] as Array<{ fecha: string; total: number }>,
  tendencia_pendientes_anterior: [] as Array<{ fecha: string; total: number }>,
  tendencia_en_espera: [] as Array<{ fecha: string; total: number }>,
  tendencia_completadas: [] as Array<{ fecha: string; total: number }>,
  tendencia_negadas: [] as Array<{ fecha: string; total: number }>,
  tendencia_negadas_anterior: [] as Array<{ fecha: string; total: number }>,
  tasa_aceptacion: 0,
  top_especialidades_serie: [] as TopConSerie[],
  top_clinicas: [] as TopConSerie[],
  sla: { bandas: [] as Array<{ etiqueta: string; total: number; pct: number }>, dentro_pct: 0, total: 0 },
  sla_diario: { dias: [], promedio: null, promedio_min: null, promedio_delta: null } as SlaDiario,
  por_dia_hora: [] as Array<{ dia: string; hora: string; total: number }>,
  heatmap_especialidad_mes: [] as Array<{ especialidad: string; mes: string; total: number }>,
  actividad: [] as ActividadEvento[],
});

const filtros = ref<{ desde: string; hasta: string; estado: string; especialidad: string; clinica: number | '' }>({
  desde: '',
  hasta: '',
  estado: 'todas',
  especialidad: '',
  clinica: '',
});

/** Período + fecha ancla de cada panel — 'todo' mantiene los filtros generales. */
const periodos = reactive<Record<string, { p: Periodo; ref: string }>>({
  donut: { p: 'todo', ref: '' },
  actividad: { p: 'todo', ref: '' },
  especialidades: { p: 'todo', ref: '' },
  sla: { p: 'todo', ref: '' },
  clinicas: { p: 'todo', ref: '' }, // sin selector — siempre histórico completo
  recientes: { p: 'todo', ref: '' }, // sin selector de período — siempre las últimas
  diahora: { p: 'todo', ref: '' },
  heatmap: { p: 'todo', ref: '' },
});
watch(periodos, () => cargarStats());

const especialidadesOpciones = computed(() => stats.value.filtros?.especialidades ?? []);
const clinicasOpciones = computed(() => stats.value.filtros?.clinicas ?? []);

const rangoFechas = computed<[string, string] | null>({
  get: (): [string, string] | null => (filtros.value.desde && filtros.value.hasta) ? [filtros.value.desde, filtros.value.hasta] : null,
  set: (val: [string, string] | null) => {
    filtros.value.desde = val?.[0] ?? '';
    filtros.value.hasta = val?.[1] ?? '';
  },
});

function limpiarFiltros() {
  filtros.value = { desde: '', hasta: '', estado: 'todas', especialidad: '', clinica: '' };
  cargarStats();
}

function aplicarFiltros() {
  cargarStats();
}

const displayStats = ref([0, 0, 0]);
let animFrame = 0;

/** Anima desde el valor actual hacia el nuevo — en refrescos de fondo sin
 * cambios ni siquiera se mueve (evita el barrido 0→N cada 30 s). */
function animateCounters(targets: number[]) {
  const desde = [...displayStats.value];
  if (desde.every((d, i) => d === targets[i])) return;
  if (animFrame) cancelAnimationFrame(animFrame);
  const inicio = performance.now();
  const dur = 700;
  const tick = (t: number) => {
    const k = Math.min(1, (t - inicio) / dur);
    const e = 1 - Math.pow(1 - k, 3);
    displayStats.value = targets.map((o, i) => Math.round(desde[i] + (o - desde[i]) * e));
    if (k < 1) {
      animFrame = requestAnimationFrame(tick);
    } else {
      animFrame = 0;
    }
  };
  animFrame = requestAnimationFrame(tick);
}

/** % de cambio entre dos series del mismo tamaño (período vs período anterior). */
function cambioVsAnterior(actual: Array<{ total: number }>, anterior: Array<{ total: number }>): number {
  const a = actual.reduce((acc, t) => acc + t.total, 0);
  const b = anterior.reduce((acc, t) => acc + t.total, 0);
  if (b === 0) return a > 0 ? 100 : 0;
  return Math.round(((a - b) / b) * 100);
}

function comparacionTexto(pct: number): string {
  return `${pct >= 0 ? '↑' : '↓'}${Math.abs(pct)}% vs período anterior`;
}

/** Comparación de negadas: menos rechazos es mejor — pill verde cuando
 *  bajaron vs la quincena anterior, roja cuando subieron. */
const negadasComparacion = computed(() => {
  const d = cambioVsAnterior(stats.value.tendencia_negadas, stats.value.tendencia_negadas_anterior);
  if (d === 0) return 'sin cambios';
  return d < 0 ? `↑${Math.abs(d)}% menos que antes` : `↓${d}% más que antes`;
});

const statCards = computed((): { label: string; value: string; icon: typeof ClipboardListIcon; tone: StatCardTone; delta: string; percent: number; sparkline?: number[]; comparacion?: string }[] => {
  const cambioPend = cambioVsAnterior(stats.value.tendencia_pendientes, stats.value.tendencia_pendientes_anterior);
  const aceptadas = stats.value.solicitudes.en_espera + stats.value.solicitudes.completadas;
  const resueltas = aceptadas + stats.value.solicitudes.negadas;
  return [
    {
      label: 'Solicitudes totales',
      value: String(displayStats.value[0]),
      icon: ClipboardListIcon,
      tone: 'info',
      delta: `${stats.value.solicitudes.pendientes} pend.`,
      percent: stats.value.solicitudes.total ? Math.round(((stats.value.solicitudes.en_espera + stats.value.solicitudes.completadas) / stats.value.solicitudes.total) * 100) : 0,
      sparkline: stats.value.tendencia.map(t => t.total),
      comparacion: comparacionTexto(cambioVsAnterior(stats.value.tendencia, stats.value.tendencia_anterior)),
    },
    {
      label: 'Negadas',
      value: String(displayStats.value[2]),
      icon: XCircleIcon,
      tone: 'danger',
      delta: `${stats.value.solicitudes.total ? Math.round((stats.value.solicitudes.negadas / stats.value.solicitudes.total) * 100) : 0}% del total`,
      percent: 0,
      sparkline: stats.value.tendencia_negadas.map(t => t.total),
      comparacion: negadasComparacion.value,
    },
    {
      label: 'Pendientes de gestión',
      value: String(displayStats.value[1]),
      icon: ClockIcon,
      tone: 'warning',
      delta: `${stats.value.solicitudes.en_espera} aceptadas`,
      percent: stats.value.solicitudes.total ? Math.round((stats.value.solicitudes.pendientes / stats.value.solicitudes.total) * 100) : 0,
      sparkline: stats.value.tendencia_pendientes.map(t => t.total),
      comparacion: comparacionTexto(cambioPend),
    },
    {
      label: 'Tasa de aceptación',
      value: `${stats.value.tasa_aceptacion}%`,
      icon: CheckCircleIcon,
      tone: 'success',
      delta: `${stats.value.solicitudes.negadas} negadas`,
      percent: 0,
      sparkline: stats.value.tendencia_en_espera.map((t, i) => t.total + (stats.value.tendencia_completadas[i]?.total ?? 0)),
      comparacion: `${aceptadas} de ${resueltas} resueltas`,
    },
  ];
});

const donutBars = computed(() => {
  const s = stats.value.distribucion;
  const total = Math.max(1, s.total);
  return [
    { label: 'Completadas', value: s.completadas, color: '#22c55e', pct: Math.round((s.completadas / total) * 100) },
    { label: 'Aceptadas', value: s.en_espera, color: '#3b82f6', pct: Math.round((s.en_espera / total) * 100) },
    { label: 'Pendientes', value: s.pendientes, color: '#f59e0b', pct: Math.round((s.pendientes / total) * 100) },
    { label: 'Negadas', value: s.negadas, color: '#ef4444', pct: Math.round((s.negadas / total) * 100) },
    { label: 'Canceladas', value: s.canceladas ?? 0, color: '#94a3b8', pct: Math.round(((s.canceladas ?? 0) / total) * 100) },
  ];
});

const estadoDonutSeries = computed(() => donutBars.value.map(b => b.value));

// Estado hovereado — se muestra en el centro de la dona y en el tooltip
// flotante teleportado al body.
const donutHover = ref<{ label: string; value: number; pct: number; color: string } | null>(null);

const estadoDonutOptions = computed(() => ({
  chart: {
    type: 'donut' as const,
    fontFamily: 'inherit',
    animations: { enabled: true, speed: 700 },
    events: {
      dataPointMouseEnter: (e: MouseEvent, _ctx: unknown, config: { dataPointIndex?: number }) => {
        const item = donutBars.value[config.dataPointIndex ?? -1];
        donutHover.value = item ?? null;
        if (item) {
          showTip(e.clientX, e.clientY - 12, item.label, [
            { color: item.color, label: 'Solicitudes:', value: item.value },
            { color: item.color, label: 'Del total:', value: `${item.pct}%` },
          ]);
        }
      },
      dataPointMouseLeave: () => { donutHover.value = null; hideTip(); },
    },
  },
  labels: donutBars.value.map(b => b.label),
  colors: donutBars.value.map(b => b.color),
  legend: { show: false },
  dataLabels: { enabled: false },
  stroke: { width: 2, colors: [isDark.value ? '#0f172a' : '#fff'] },
  plotOptions: {
    pie: {
      customScale: 0.96,
      donut: { size: '74%', labels: { show: false } },
      expandOnClick: false,
    },
  },
  states: {
    hover: { filter: { type: 'lighten', value: 0.06 } },
    active: { filter: { type: 'none' } },
  },
  grid: { padding: { top: 0, right: 0, bottom: 0, left: 0 } },
  tooltip: { enabled: false },
}));

/** Granularidad del gráfico de tendencia (día / semana / mes / año) + fecha ancla. */
const granularidad = ref<string>('dia');
const fechaTendencia = ref('');
watch([granularidad, fechaTendencia], () => cargarStats());

/** Área: solicitudes por período con la línea punteada del período anterior. */
const diaCats = computed(() => stats.value.tendencia.map(t => t.label));

const diaChartSeries = computed(() => [
  { name: 'Actual', type: 'area', data: stats.value.tendencia.map(t => t.total) },
]);
const diaChartOptions = computed(() => ({
  chart: {
    fontFamily: 'inherit',
    toolbar: { show: false },
    animations: { enabled: true, speed: 600 },
    zoom: { enabled: false },
    dropShadow: { enabled: true, enabledOnSeries: [0], top: 4, left: 0, blur: 6, color: '#16468e', opacity: 0.35 },
    events: {
      mouseMove: (e: MouseEvent, _ctx: unknown, config: { dataPointIndex?: number }) => {
        const i = config?.dataPointIndex ?? -1;
        const punto = stats.value.tendencia[i];
        if (i < 0 || !punto) { hideTip(); return; }
        showTip(e.clientX, e.clientY - 12, punto.label, [
          { color: '#16468e', label: 'Solicitudes:', value: stats.value.tendencia[i]?.total ?? 0 },
        ]);
      },
      mouseLeave: hideTip,
    },
  },
  colors: ['#16468e'],
  stroke: { curve: 'smooth' as const, width: [3], lineCap: 'round' as const },
  fill: {
    type: 'gradient',
    gradient: { shade: 'light', type: 'vertical', gradientToColors: ['#60a5fa'], opacityFrom: 0.42, opacityTo: 0.02, stops: [0, 85, 100] },
  },
  markers: {
    size: [0],
    colors: ['#fff'],
    strokeColors: ['#16468e'],
    strokeWidth: [2.5],
    hover: { sizeOffset: 4 },
  },
  dataLabels: { enabled: false },
  xaxis: {
    categories: diaCats.value,
    labels: { style: { fontSize: '9.5px', colors: '#94a3b8' }, rotate: 0, hideOverlappingLabels: true },
    axisBorder: { show: false },
    axisTicks: { show: false },
    tooltip: { enabled: false },
    crosshairs: {
      show: true,
      position: 'back' as const,
      stroke: { color: '#93c5fd', width: 1, dashArray: 4 },
    },
  },
  yaxis: {
    labels: { style: { fontSize: '9.5px', colors: '#94a3b8' } },
  },
  grid: {
    borderColor: isDark.value ? '#1e293b' : '#f1f5f9',
    strokeDashArray: 4,
    padding: { top: 0, right: 8, bottom: 0, left: 8 },
  },
  legend: { show: false },
  /* El tooltip debe estar habilitado para que Apex calcule el
     dataPointIndex del hover — se devuelve vacío y se oculta por CSS;
     el tooltip visible es el flotante teleportado a <body>. */
  tooltip: {
    enabled: true,
    shared: true,
    intersect: false,
    custom: () => '',
  },
}));

/** Tabla de especialidades: icono + color propio por fila, como el mockup. */
const ESP_PALETA = [
  { color: '#2563eb', tint: '#dbeafe', spark: '#3b82f6', icon: StethoscopeIcon },
  { color: '#16a34a', tint: '#dcfce7', spark: '#22c55e', icon: HeartIcon },
  { color: '#ea580c', tint: '#ffedd5', spark: '#f97316', icon: SparklesIcon },
  { color: '#0d9488', tint: '#ccfbf1', spark: '#14b8a6', icon: BoneIcon },
  { color: '#7c3aed', tint: '#ede9fe', spark: '#8b5cf6', icon: BrainIcon },
];
const especialidadRows = computed(() => {
  const filas = stats.value.top_especialidades_serie;
  const max = Math.max(1, ...filas.map(f => f.total));
  return filas.map((f, i) => ({
    ...f,
    barPct: Math.round((f.total / max) * 100),
    ...ESP_PALETA[i % ESP_PALETA.length],
  }));
});

/** Sparklines de top clínicas: un color distinto por fila como el mockup. */
const CLI_SPARK = ['#22c55e', '#3b82f6', '#f59e0b', '#8b5cf6', '#14b8a6'];

/**
 * SLA dibujado a mano (HTML + SVG): ApexCharts 6 tiene un bug conocido con
 * `stacked` + `stackOnlyBar` en gráficos mixtos (issue #5105) que deja las
 * barras invisibles. Con SVG propio tenemos control total sobre el render.
 */
const slaColors = ['#22c55e', '#f59e0b', '#ef4444'];

/* ── Tooltip flotante unificado (teleportado a <body>) ──
   Lo usan el SLA, la dona, el heatmap y la tendencia: los tooltips de
   ApexCharts quedan siempre dentro del área del gráfico y se cortan con
   el overflow:hidden de las tarjetas. */
type TipRow = { color?: string; line?: boolean; label: string; value: string | number };
const floatingTip = ref<{ x: number; y: number; title: string; rows: TipRow[] } | null>(null);

function showTip(x: number, y: number, title: string, rows: TipRow[]): void {
  floatingTip.value = { x, y, title, rows };
}
function hideTip(): void {
  floatingTip.value = null;
}

function slaHoverShow(e: MouseEvent, i: number): void {
  const d = stats.value.sla_diario.dias[i];
  if (!d) return;
  const r = (e.currentTarget as HTMLElement).getBoundingClientRect();
  showTip(r.left + r.width / 2, r.top, d.etiqueta, [
    { color: '#22c55e', label: '≤ 2h:', value: d.hasta2 },
    { color: '#f59e0b', label: '2–4h:', value: d.de2a4 },
    { color: '#ef4444', label: '> 4h:', value: d.mas4 },
    { line: true, label: 'Prom:', value: `${d.avg_horas}h` },
  ]);
}

const slaMaxTotal = computed(() => {
  const max = Math.max(1, ...stats.value.sla_diario.dias.map(d => d.hasta2 + d.de2a4 + d.mas4));
  // Redondea hacia arriba al múltiplo de 5 más cercano para ticks limpios.
  return Math.ceil(max / 5) * 5;
});

const slaTicks = computed(() => [0, 0.25, 0.5, 0.75, 1].map(f => ({
  v: Math.round(slaMaxTotal.value * f),
  pct: f * 100,
})));

function slaSegH(v: number): number {
  return (v / slaMaxTotal.value) * 100;
}

/** La línea de promedio se escala a su propio rango (con margen arriba). */
const slaMaxAvg = computed(() => Math.max(1, ...stats.value.sla_diario.dias.map(d => d.avg_horas)) * 1.35);

const slaLinePoints = computed(() => {
  const n = stats.value.sla_diario.dias.length;
  return stats.value.sla_diario.dias.map((d, i) => ({
    x: ((i + 0.5) / n) * 100,
    y: 100 - (d.avg_horas / slaMaxAvg.value) * 100,
  }));
});

/** Path suavizado (catmull-rom → bezier) a través de los puntos promedio. */
const slaLinePath = computed(() => {
  const pts = slaLinePoints.value;
  if (!pts.length) return '';
  if (pts.length === 1) return `M ${pts[0].x} ${pts[0].y}`;
  let d = `M ${pts[0].x} ${pts[0].y}`;
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[i - 1] ?? pts[i];
    const p1 = pts[i];
    const p2 = pts[i + 1];
    const p3 = pts[i + 2] ?? p2;
    const c1x = p1.x + (p2.x - p0.x) / 6;
    const c1y = p1.y + (p2.y - p0.y) / 6;
    const c2x = p2.x - (p3.x - p1.x) / 6;
    const c2y = p2.y - (p3.y - p1.y) / 6;
    d += ` C ${c1x} ${c1y}, ${c2x} ${c2y}, ${p2.x} ${p2.y}`;
  }
  return d;
});

/* ── Mapa de calor día × hora (también SVG-free: celdas de color) ── */
const heatDias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const heatHoras = ['00', '02', '04', '06', '08', '10', '12', '14', '16', '18', '20', '22'];

const heatMapa = computed(() => {
  const m = new Map<string, number>();
  let max = 0;
  for (const c of stats.value.por_dia_hora) {
    const key = `${c.dia}|${c.hora.slice(0, 2)}`;
    m.set(key, c.total);
    if (c.total > max) max = c.total;
  }
  return { m, max: Math.max(1, max) };
});

function heatTotal(dia: string, hora: string): number {
  return heatMapa.value.m.get(`${dia}|${hora}`) ?? 0;
}

/** Mezcla un color base y un acento hex en proporción t (0→base, 1→acento). */
function mixHex(accent: string, base: string, t: number): string {
  const parse = (h: string): number[] => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)];
  const [r1, g1, b1] = parse(base);
  const [r2, g2, b2] = parse(accent);
  const mix = (a: number, b: number): number => Math.round(a + (b - a) * t);
  return `rgb(${mix(r1, r2)}, ${mix(g1, g2)}, ${mix(b1, b2)})`;
}

/** Escala azul de 0 a 1 — intensidad proporcional al máximo del mapa. */
function heatScale(t: number): string {
  return mixHex('#2563eb', isDark.value ? '#1e2536' : '#eef4ff', 0.08 + t * 0.92);
}

function heatColor(dia: string, hora: string): string {
  return heatScale(heatTotal(dia, hora) / heatMapa.value.max);
}

function heatTip(e: MouseEvent, dia: string, hora: string): void {
  const r = (e.currentTarget as HTMLElement).getBoundingClientRect();
  const t = heatTotal(dia, hora);
  showTip(r.left + r.width / 2, r.top, `${dia} · ${hora}:00 – ${hora}:59`, [
    { color: '#2563eb', label: 'Solicitudes:', value: t },
  ]);
}

/* ── Mapa de calor: especialidad × mes (últimos 6 meses) ── */
const espMesSeries = computed(() => {
  const esps: string[] = [];
  for (const c of stats.value.heatmap_especialidad_mes) {
    if (!esps.includes(c.especialidad)) esps.push(c.especialidad);
  }
  return esps.map(esp => ({
    name: esp,
    data: stats.value.heatmap_especialidad_mes
      .filter(c => c.especialidad === esp)
      .map(c => ({ x: c.mes, y: c.total })),
  })).reverse();
});

const espMesMax = computed(() =>
  Math.max(1, ...espMesSeries.value.flatMap(s => s.data.map(d => d.y)))
);

/** Escala violeta: del tinte base del tema al #7048e8 puro. */
function espMesColor(t: number): string {
  return mixHex('#7048e8', isDark.value ? '#1e2536' : '#f4f2ff', 0.10 + t * 0.90);
}

/* colorScale.ranges apaga el sombreado automático de Apex — así los
   "0" se ven del color del tema (oscuro en dark mode) en vez de
   quedar siempre casi blancos. */
const espMesRanges = computed(() => {
  const steps = 6;
  const max = espMesMax.value;
  return Array.from({ length: steps }, (_, i) => ({
    from: Math.round((max * i) / steps),
    to: Math.round((max * (i + 1)) / steps),
    color: espMesColor(i / (steps - 1)),
    name: '',
  }));
});

const espMesOptions = computed(() => ({
  chart: {
    type: 'heatmap' as const,
    fontFamily: 'inherit',
    toolbar: { show: false },
    events: {
      dataPointMouseEnter: (e: MouseEvent, _ctx: unknown, opts: { seriesIndex: number; dataPointIndex: number }) => {
        const esp = espMesSeries.value[opts.seriesIndex]?.name;
        const celda = espMesSeries.value[opts.seriesIndex]?.data[opts.dataPointIndex];
        if (esp && celda) {
          showTip(e.clientX, e.clientY - 12, `${esp} · ${celda.x}`, [
            { color: '#7048e8', label: 'Solicitudes:', value: celda.y },
          ]);
        }
      },
      dataPointMouseLeave: hideTip,
    },
  },
  dataLabels: { enabled: false },
  legend: { show: false },
  colors: ['#7048e8'],
  theme: { mode: isDark.value ? 'dark' : 'light' },
  xaxis: {
    labels: { style: { fontSize: '8px', colors: isDark.value ? '#64748b' : '#94a3b8' }, rotate: 0 },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: { labels: { style: { fontSize: '8px', colors: isDark.value ? '#94a3b8' : '#475569' }, maxWidth: 80 } },
  grid: {
    borderColor: isDark.value ? 'rgba(255,255,255,0.06)' : '#f1f5f9',
    padding: { left: 2, right: 2 },
  },
  plotOptions: {
    heatmap: {
      radius: 4,
      enableShades: false,
      colorScale: { ranges: espMesRanges.value, min: 0, max: espMesMax.value },
    },
  },
  tooltip: { enabled: false },
}));

/** Icono/colores por tipo de evento del feed de actividad. */
function actividadTono(tipo: string): { icon: typeof ActivityIcon; color: string; bg: string } {
  switch (tipo) {
    case 'aceptada': return { icon: CheckCircleIcon, color: '#16a34a', bg: '#dcfce7' };
    case 'rechazada': return { icon: XCircleIcon, color: '#dc2626', bg: '#fee2e2' };
    case 'cancelada': return { icon: XCircleIcon, color: '#64748b', bg: '#e2e8f0' };
    case 'en_espera': return { icon: ClockIcon, color: '#d97706', bg: '#fef3c7' };
    case 'usuario': return { icon: UserPlusIcon, color: '#7c3aed', bg: '#ede9fe' };
    default: return { icon: FilePlusIcon, color: '#0d9488', bg: '#ccfbf1' };
  }
}

/** Avatar con iniciales del paciente — color estable derivado del nombre. */
const PAC_PALETA = [
  { bg: '#dbeafe', color: '#1d4ed8' },
  { bg: '#dcfce7', color: '#15803d' },
  { bg: '#fef3c7', color: '#b45309' },
  { bg: '#ede9fe', color: '#6d28d9' },
  { bg: '#fce7f3', color: '#be185d' },
  { bg: '#ccfbf1', color: '#0f766e' },
];
function pacIniciales(nombre: string): string {
  return (nombre || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
}
function pacAvatarColor(nombre: string): { bg: string; color: string } {
  let h = 0;
  for (const ch of nombre || '') h = (h * 31 + ch.charCodeAt(0)) % 997;
  return PAC_PALETA[h % PAC_PALETA.length];
}

function estadoLabel(estado: string): string {
  return {
    pendiente: 'Pendiente',
    en_espera: 'Aceptado',
    completado: 'Completada',
    negado: 'Negada',
    cancelado: 'Cancelada',
  }[estado] ?? estado;
}

/** "Hace 5 min" / "Hace 2 horas" / "Hace 3 días" a partir de un ISO string. */
function haceTexto(iso: string | null): string {
  if (!iso) return '';
  const minutos = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
  if (minutos < 1) return 'Ahora';
  if (minutos < 60) return `Hace ${minutos} min`;
  const horas = Math.floor(minutos / 60);
  if (horas < 24) return `Hace ${horas} ${horas === 1 ? 'hora' : 'horas'}`;
  const dias = Math.floor(horas / 24);
  return `Hace ${dias} ${dias === 1 ? 'día' : 'días'}`;
}

function verSolicitud(id: number) {
  router.push({ path: '/solicitudes-referencia', query: { resaltar: id } });
}

async function descargarPdf(id: number) {
  try {
    pdfCargando.value = id;
    const { data } = await http.get(`/api/solicitudes-referencia/${id}`);
    await exportarSolicitudPdf(data.data ?? data);
  } catch {
    // silencioso — el usuario puede reintentar
  } finally {
    pdfCargando.value = null;
  }
}

async function cargarStats(silencioso = false) {
  try {
    cargando.value = true;
    const params: Record<string, string> = { granularidad: granularidad.value };
    if (fechaTendencia.value) params.ref_tendencia = fechaTendencia.value;
    for (const [key, sel] of Object.entries(periodos)) {
      params[`periodo_${key}`] = sel.p;
      if (sel.p !== 'todo' && sel.ref) params[`ref_${key}`] = sel.ref;
    }
    if (filtros.value.desde) params.desde = filtros.value.desde;
    if (filtros.value.hasta) params.hasta = filtros.value.hasta;
    if (filtros.value.estado && filtros.value.estado !== 'todas') params.estado = filtros.value.estado;
    if (filtros.value.especialidad) params.especialidad = filtros.value.especialidad;
    if (filtros.value.clinica) params.clinica = String(filtros.value.clinica);
    const { data } = await http.get('/api/dashboard/stats', { params });
    stats.value = { ...stats.value, ...data.data };
    errorCarga.value = false;
    const objetivos = [
      stats.value.solicitudes.total,
      stats.value.solicitudes.pendientes,
      stats.value.solicitudes.negadas,
    ];
    if (silencioso) {
      displayStats.value = objetivos;
    } else {
      animateCounters(objetivos);
    }
  } catch {
    errorCarga.value = true;
  } finally {
    cargando.value = false;
    primeraCarga.value = false;
  }
}

onMounted(cargarStats);

// Refresco silencioso cada 30 s sin mostrar el esqueleto de carga.
usePolling(() => {
  if (!cargando.value) cargarStats(true);
}, 30000);
</script>

<style scoped>
/* ── Background aurora ── */
.dashboard-bg {
  position: relative;
  background: linear-gradient(180deg, #f6f9fe 0%, #eef3fa 100%);
}
.dark .dashboard-bg { background: transparent; }
.dashboard-bg::before {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  background:
    radial-gradient(ellipse 45% 40% at 88% -5%, rgba(96, 165, 250, 0.30), transparent 70%),
    radial-gradient(ellipse 40% 42% at -5% 105%, rgba(52, 211, 153, 0.24), transparent 70%),
    radial-gradient(ellipse 34% 34% at 55% 115%, rgba(167, 139, 250, 0.20), transparent 70%),
    radial-gradient(ellipse 26% 26% at 15% -8%, rgba(56, 189, 248, 0.18), transparent 70%);
  animation: auroraShift 20s ease-in-out infinite alternate;
}
.dark .dashboard-bg::before {
  background:
    radial-gradient(ellipse 45% 40% at 88% -5%, rgba(37, 99, 235, 0.12), transparent 70%),
    radial-gradient(ellipse 40% 42% at -5% 105%, rgba(16, 185, 129, 0.08), transparent 70%);
}
@keyframes auroraShift {
  from { transform: translateY(0) scale(1); }
  to { transform: translateY(-2.5%) scale(1.06); }
}

/* ── Saludo ── */
.dash-title { font-size: 20px; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; }
.dark .dash-title { color: #e2e8f0; }
.dash-subtitle { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.dark .dash-subtitle { color: #64748b; }

/* ── Filtros ── */
.dash-clear-btn {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 600; color: #64748b;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
  padding: 7px 12px; cursor: pointer; transition: all .15s ease;
}
.dash-clear-btn:hover { border-color: var(--rf-primary); color: var(--rf-primary); }
.dark .dash-clear-btn { background: #161b28; border-color: rgba(255,255,255,0.08); color: #cbd5e1; }
.dark .dash-clear-btn:hover { border-color: var(--rf-primary); color: #a5b4fc; }

/* ── Aviso de error ── */
.dash-error-banner {
  display: flex; align-items: center; gap: 8px;
  background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
  border-radius: 10px; padding: 8px 12px; font-size: 12px; font-weight: 600;
}
.dash-error-banner button {
  margin-left: auto; background: #fff; border: 1px solid #fecaca; color: #b91c1c;
  border-radius: 8px; padding: 4px 10px; font-size: 11px; font-weight: 700; cursor: pointer;
}
.dash-error-banner button:hover { background: #fee2e2; }
.dark .dash-error-banner { background: rgba(220,38,38,0.12); border-color: rgba(220,38,38,0.3); color: #fca5a5; }
.dark .dash-error-banner button { background: transparent; border-color: rgba(220,38,38,0.3); color: #fca5a5; }

/* ── Skeleton de tarjetas ── */
.stat-card-pastel-skeleton {
  background: #fff; border: 1px solid rgba(15, 23, 42, 0.04);
}
.dark .stat-card-pastel-skeleton { background: #161b28; }

/* ── Panel cards ── */
.panel-card-ui {
  background: rgba(255,255,255,0.78);
  border: 1px solid rgba(255,255,255,0.85);
  border-radius: 16px;
  box-shadow:
    0 10px 30px rgba(22, 70, 142, .10),
    inset 0 1px 0 rgba(255,255,255,0.9);
  transition: transform .3s cubic-bezier(.22,1,.36,1), box-shadow .3s cubic-bezier(.22,1,.36,1);
  backdrop-filter: blur(18px) saturate(1.35);
  position: relative;
  overflow: hidden;
  min-height: 0;
}
.panel-card-ui::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, #0D2D6B, #2563eb, #38bdf8, #16468E);
  background-size: 220% 100%;
  animation: accentSlide 7s linear infinite;
}
@keyframes accentSlide {
  from { background-position: 0% 0; }
  to { background-position: 220% 0; }
}
.panel-card-ui:hover {
  transform: translateY(-2px);
  box-shadow:
    0 18px 40px rgba(22,70,142,.15),
    inset 0 1px 0 rgba(255,255,255,0.9);
}
.dark .panel-card-ui {
  background: #161b28;
  border-color: rgba(255,255,255,0.07);
}

.panel-head {
  display: flex;
  align-items: center;
  gap: 8px;
  row-gap: 5px;
  flex-wrap: wrap;
  margin-bottom: 7px;
  position: relative;
  z-index: 1;
}
.panel-head-icon {
  width: 24px; height: 24px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #2563eb, #1e40af);
  color: #fff;
  box-shadow: 0 4px 10px rgba(37, 99, 235, 0.35);
  flex-shrink: 0;
}
.panel-head-title {
  font-size: 12.5px;
  font-weight: 800;
  color: #1e2d55;
  margin: 0;
  flex: 1;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dark .panel-head-title { color: #e2e8f0; }
.panel-head-pill {
  font-size: 9px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 999px;
  background: #e0ecff;
  color: #16468e;
  border: 1px solid #d3e3fb;
  flex-shrink: 0;
}
.dark .panel-head-pill { background: rgba(99,102,241,0.15); color: #a5b4fc; border-color: rgba(99,102,241,0.25); }
.panel-link, .panel-link-sm {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 10.5px;
  font-weight: 700;
  color: #16468e;
  text-decoration: none;
  transition: color .15s ease, gap .15s ease;
}
.panel-link {
  margin-top: auto;
  padding-top: 9px;
  border-top: 1px solid #f1f5f9;
  justify-content: center;
  position: relative;
  z-index: 1;
}
.panel-link-sm { flex-shrink: 0; }
.panel-link:hover, .panel-link-sm:hover { color: #2563c4; gap: 8px; }
.dark .panel-link { border-top-color: rgba(255,255,255,0.07); color: #a5b4fc; }
.dark .panel-link-sm { color: #a5b4fc; }

.panel-empty {
  height: 100%;
  min-height: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 600;
}

/* ── Leyenda del gráfico de tendencia ── */
.chart-legend { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.chart-legend-pill {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 9.5px; font-weight: 700;
  padding: 3px 9px; border-radius: 999px;
}
.chart-legend-pill--actual { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.chart-legend-pill--anterior { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
.chart-legend-pill--prom { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.chart-legend-dash { width: 12px; height: 0; border-top: 2px dashed #f59e0b; }
.dark .chart-legend-pill--prom { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }
.chart-legend-dot { width: 7px; height: 7px; border-radius: 50%; }
.dark .chart-legend-pill--actual { background: rgba(37,99,235,0.15); color: #93c5fd; border-color: rgba(37,99,235,0.3); }
.dark .chart-legend-pill--anterior { background: rgba(255,255,255,0.06); color: #94a3b8; border-color: rgba(255,255,255,0.1); }

/* El tooltip nativo del chart de tendencia va oculto (custom vacío) —
   el visible es el flotante .sla-tip teleportado a <body>. */
:deep(.apexcharts-tooltip) { display: none !important; }


.estado-donut-empty {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  text-align: center;
  font-size: 10px; font-weight: 700; color: #94a3b8;
  line-height: 1.5;
}
.dark .estado-donut-empty { color: #64748b; }

/* ── Dona de estados ── */
/* La dona llena el alto disponible de la tarjeta — se adapta a cualquier
   pantalla sin salirse del contenedor. */
.estado-donut-wrap { position: relative; flex: 1; min-width: 0; height: 100%; min-height: 0; }
.estado-donut-wrap :deep(.apexcharts-canvas) { margin: 0 auto; }
/* El contenido central se acota al hueco real de la dona (~60% del ancho)
   para que las etiquetas no se monten sobre el anillo. */
.estado-donut-center { position: absolute; inset: 0; padding: 0 20%; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
.estado-donut-num { font-size: 20px; font-weight: 800; color: #1e293b; line-height: 1.1; }
.dark .estado-donut-num { color: #e2e8f0; }
.estado-donut-label { font-size: 9px; color: #94a3b8; font-weight: 600; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dark .estado-donut-label { color: #64748b; }

/* Leyenda lateral de la dona */
.estado-legend {
  display: flex;
  flex-direction: column;
  gap: 7px;
  flex-shrink: 0;
  min-width: 0;
}
.estado-legend-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.estado-legend-top {
  display: grid;
  grid-template-columns: 8px 1fr auto auto;
  align-items: center;
  gap: 6px;
}
.estado-legend-bar {
  height: 3px;
  border-radius: 999px;
  background: #eef2f7;
  overflow: hidden;
  margin-left: 14px;
}
.dark .estado-legend-bar { background: rgba(255,255,255,0.07); }
.estado-legend-fill {
  height: 100%;
  border-radius: 999px;
  transition: width .8s cubic-bezier(.22,1,.36,1);
}
.estado-legend-dot { width: 8px; height: 8px; border-radius: 50%; }
.estado-legend-name { font-size: 10px; font-weight: 600; color: #64748b; white-space: nowrap; }
.dark .estado-legend-name { color: #94a3b8; }
.estado-legend-val { font-size: 10.5px; font-weight: 800; color: #1e293b; text-align: right; }
.dark .estado-legend-val { color: #e2e8f0; }
.estado-legend-pct { font-size: 9.5px; font-weight: 700; color: #94a3b8; text-align: right; min-width: 26px; }

/* ── Tablas (especialidades, top clínicas, recientes) ── */
.dash-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
  position: relative;
  z-index: 1;
}
.dash-table thead th {
  font-size: 9.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #94a3b8;
  text-align: left;
  padding: 3px 8px 5px;
  border-bottom: 1px solid #eef2f7;
  white-space: nowrap;
}
.dark .dash-table thead th { color: #64748b; border-bottom-color: rgba(255,255,255,0.07); }
.dash-table tbody td {
  padding: 3.5px 8px;
  border-bottom: 1px solid #f4f7fb;
  vertical-align: middle;
}
.dark .dash-table tbody td { border-bottom-color: rgba(255,255,255,0.05); }
.dash-table tbody tr:last-child td { border-bottom: none; }
.dash-table tbody tr { transition: background .12s ease; }
.dash-table tbody tr:hover { background: #f8fafc; }
.dark .dash-table tbody tr:hover { background: rgba(255,255,255,0.03); }

.tbl-name {
  font-size: 10.5px;
  font-weight: 700;
  color: #334155;
  margin: 0;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  max-width: 200px;
}
.tbl-name--esp { text-transform: capitalize; max-width: 180px; }
.dark .tbl-name { color: #cbd5e1; }
.tbl-cell { font-size: 11px; color: #475569; }
.tbl-ellipsis { margin: 0; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dark .tbl-cell { color: #94a3b8; }
.tbl-num { font-weight: 800; font-size: 11.5px; color: #1e293b; }
.dark .tbl-num { color: #e2e8f0; }
.tbl-sub { font-size: 10px; color: #94a3b8; font-weight: 600; }
.tbl-empty { text-align: center; padding: 18px 8px; color: #94a3b8; font-size: 11px; font-weight: 600; }
.tbl-bar-track {
  height: 3px;
  border-radius: 999px;
  background: #eef2f7;
  overflow: hidden;
  margin-top: 2.5px;
  max-width: 160px;
}
.dark .tbl-bar-track { background: rgba(255,255,255,0.06); }
.tbl-bar-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #16468E, #3b82f6);
  transition: width .8s cubic-bezier(.22,1,.36,1);
}

/* ── Iconos de especialidad ── */
.esp-icon {
  width: 20px; height: 20px;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.esp-icon svg { width: 12px; height: 12px; }

/* ── Ranking de clínicas ── */
.top-rank {
  width: 20px; height: 20px;
  border-radius: 7px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 800;
  flex-shrink: 0;
  background: #e0ecff;
  color: #16468e;
}
.top-rank-1 { background: linear-gradient(135deg, #fde68a, #fbbf24); color: #92400e; }
.top-rank-2 { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); color: #475569; }
.top-rank-3 { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #9a3412; }

/* ── Gráfico SLA propio (barras apiladas + línea SVG) ── */
.sla-legend {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}
.sla-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 8.5px;
  font-weight: 700;
  color: #64748b;
}
.sla-legend-item i {
  width: 8px; height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.sla-legend-item i.sla-legend-line,
.sla-legend-line {
  width: 12px !important;
  height: 2.5px !important;
  border-radius: 2px !important;
  background: #1e3a5f;
}
.dark .sla-legend-item { color: #94a3b8; }
.dark .sla-legend-line { background: #93c5fd; }

.sla-plot {
  position: relative;
  flex: 1;
  min-height: 0;
}
.sla-gridline {
  position: absolute;
  left: 0; right: 0;
  border-top: 1px dashed #eef2f7;
}
.dark .sla-gridline { border-top-color: rgba(255,255,255,0.06); }
.sla-gridlabel {
  position: absolute;
  top: -6px;
  left: 0;
  font-size: 7.5px;
  font-weight: 600;
  color: #b0bccd;
  background: transparent;
}
.sla-bars {
  position: absolute;
  inset: 0;
  left: 22px;
  display: flex;
  align-items: stretch;
  gap: 6%;
}
.sla-col {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}
.sla-seg {
  width: 100%;
  display: block;
  transition: height .7s cubic-bezier(.22,1,.36,1);
}
.sla-col .sla-seg:first-child { border-radius: 2px 2px 0 0; }
.sla-col:hover .sla-seg { filter: brightness(1.08); }

.sla-svg {
  position: absolute;
  top: 0; bottom: 0;
  left: 22px; right: 0;
  width: calc(100% - 22px);
  height: 100%;
  overflow: visible;
  pointer-events: none;
}
.sla-line-path {
  stroke-width: 2px;
  filter: drop-shadow(0 2px 5px rgba(30, 58, 95, 0.5));
}
.sla-dot {
  position: absolute;
  width: 4.5px; height: 4.5px;
  border-radius: 50%;
  background: #1e3a5f;
  border: 1.5px solid #fff;
  transform: translate(-50%, -50%);
  pointer-events: none;
  box-shadow: 0 1px 3px rgba(15,23,42,0.3);
}
.dark .sla-dot { background: #93c5fd; border-color: #161b28; }

.sla-tip {
  position: fixed;
  transform: translate(-50%, calc(-100% - 8px));
  z-index: 9999;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  padding: 6px 9px;
  box-shadow: 0 8px 20px rgba(15,23,42,0.14);
  white-space: nowrap;
  pointer-events: none;
}
.dark .sla-tip { background: #1e2536; border-color: rgba(255,255,255,0.1); }
.sla-tip-fecha { font-size: 9.5px; font-weight: 800; color: #1e293b; margin: 0 0 3px; }
.dark .sla-tip-fecha { color: #e2e8f0; }
.sla-tip-row { font-size: 9px; color: #64748b; margin: 1px 0; display: flex; align-items: center; gap: 5px; }
.sla-tip-row i { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sla-tip-row b { color: #1e293b; margin-left: auto; padding-left: 8px; }
.dark .sla-tip-row { color: #94a3b8; }
.dark .sla-tip-row b { color: #e2e8f0; }

.sla-xaxis {
  display: flex;
  gap: 6%;
  padding-left: 22px;
  margin-top: 4px;
}
.sla-xlabel {
  flex: 1;
  text-align: center;
  font-size: 7.5px;
  font-weight: 600;
  color: #b0bccd;
  white-space: nowrap;
  overflow: hidden;
}

/* ── Resumen SLA ── */
.sla-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
  position: relative;
  z-index: 1;
}
.dark .sla-summary { border-top-color: rgba(255,255,255,0.07); }
.sla-summary-item { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.sla-summary-pct { font-size: 15px; font-weight: 800; line-height: 1.1; }
.dark .sla-summary-item--prom .sla-summary-pct { color: #e2e8f0 !important; }
.sla-summary-label { font-size: 9.5px; font-weight: 700; color: #64748b; }
.dark .sla-summary-label { color: #94a3b8; }
.sla-summary-count { font-size: 9px; color: #94a3b8; font-weight: 600; }
.sla-summary-delta { font-size: 9.5px; font-weight: 800; }
.sla-summary-delta--ok { color: #16a34a; }
.sla-summary-delta--bad { color: #dc2626; }

/* ── Badges de estado ── */
.estado-badge {
  display: inline-block;
  font-size: 9.5px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 999px;
  white-space: nowrap;
}
.estado-badge--completado { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #15803d; border: 1px solid #86efac; }
.estado-badge--en_espera { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1d4ed8; border: 1px solid #93c5fd; }
.estado-badge--pendiente { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; border: 1px solid #fcd34d; }
.estado-badge--negado { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #b91c1c; border: 1px solid #fca5a5; }
.dark .estado-badge--completado { background: rgba(34,197,94,0.15); color: #4ade80; border-color: rgba(34,197,94,0.3); }
.dark .estado-badge--en_espera { background: rgba(59,130,246,0.15); color: #93c5fd; border-color: rgba(59,130,246,0.3); }
.dark .estado-badge--pendiente { background: rgba(245,158,11,0.15); color: #fbbf24; border-color: rgba(245,158,11,0.3); }
.dark .estado-badge--negado { background: rgba(239,68,68,0.15); color: #fca5a5; border-color: rgba(239,68,68,0.3); }

.tbl-action {
  width: 26px; height: 26px;
  display: inline-flex; align-items: center; justify-content: center;
  border: none; background: transparent; border-radius: 7px;
  color: #94a3b8; cursor: pointer;
  transition: background .12s ease, color .12s ease;
  margin-left: 2px;
}
.tbl-action:hover { background: #eef2f7; color: #16468e; }
.tbl-action:disabled { opacity: .4; cursor: default; }
.dark .tbl-action:hover { background: rgba(255,255,255,0.08); color: #a5b4fc; }

/* ── Mapa de calor día × hora ── */
.heatmap-grid {
  display: grid;
  grid-template-columns: 26px repeat(12, 1fr);
  grid-template-rows: auto repeat(7, minmax(0, 1fr));
  gap: 3px;
  align-items: stretch;
}
.heatmap-axis {
  font-size: 7.5px;
  font-weight: 700;
  color: #94a3b8;
  text-align: center;
  white-space: nowrap;
}
.dark .heatmap-axis { color: #64748b; }
.heatmap-axis--dia { text-align: right; padding-right: 3px; display: flex; align-items: center; justify-content: flex-end; }
.heatmap-cell {
  display: block;
  height: 100%;
  min-height: 0;
  border-radius: 3.5px;
  transition: transform .12s ease, box-shadow .12s ease;
  cursor: default;
}
.heatmap-cell:hover {
  transform: scale(1.25);
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35);
  z-index: 5;
  position: relative;
}
.heatmap-legend {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  margin-top: 8px;
  font-size: 8.5px;
  font-weight: 600;
  color: #94a3b8;
}
.heatmap-legend .heatmap-cell { width: 12px; height: 8px; min-height: 8px; flex: none; }
.heatmap-legend .heatmap-cell:hover { transform: none; box-shadow: none; }

/* ── Variante compacta de tabla (recientes al lado del heatmap) ── */
.dash-table--compact tbody td { padding: 3px 6px; }
.dash-table--compact thead th { padding: 2px 6px 4px; }
.dash-table--compact .tbl-name { font-size: 10px; max-width: 130px; }
.dash-table--compact .tbl-sub { font-size: 9px; }
.dash-table--compact .tbl-cell { font-size: 10px; }

/* ── Tabla de recientes: header pegado al hacer scroll interno ── */
.recientes-scroll { scrollbar-width: thin; }
.esp-scroll { scrollbar-width: thin; }
.dash-table--recientes thead th,
.dash-table--esp thead th {
  position: sticky;
  top: 0;
  background: #fff;
  z-index: 2;
}
.dark .dash-table--recientes thead th,
.dark .dash-table--esp thead th { background: #161b28; }

/* ── Avatar de paciente ── */
.pac-avatar {
  width: 26px; height: 26px;
  border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 9px; font-weight: 800;
  flex-shrink: 0;
  letter-spacing: 0.02em;
}

/* ── Actividad reciente ── */
.activity-list { scrollbar-width: thin; }
.activity-row {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 6px 8px;
  border-radius: 10px;
  transition: background .15s ease;
  position: relative;
  z-index: 1;
}
/* Línea de tiempo vertical conectando los iconos */
.activity-row:not(.activity-row--last) .activity-icon::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  width: 2px;
  height: 8px;
  background: linear-gradient(180deg, #dbe5f1, transparent);
  border-radius: 2px;
}
.activity-icon { position: relative; }
.activity-row:hover { background: #f8fafc; }
.dark .activity-row:hover { background: rgba(255,255,255,0.04); }
.activity-icon {
  width: 28px; height: 28px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: inset 0 -1px 0 rgba(15,23,42,0.06), 0 2px 6px rgba(15,23,42,0.08);
}
.activity-title {
  font-size: 11px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .activity-title { color: #e2e8f0; }
.activity-detail {
  font-size: 10px;
  color: #94a3b8;
  margin: 1px 0 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.activity-time {
  font-size: 9.5px;
  font-weight: 600;
  color: #b0bccd;
  flex-shrink: 0;
  white-space: nowrap;
}
.dark .activity-time { color: #64748b; }

/* ── Shimmer ── */
.shimmer-box, .shimmer-bar { position: relative; overflow: hidden; background: #e6ebf3; }
.shimmer-box { border-radius: 8px; }
.shimmer-bar { border-radius: 4px; }
.shimmer-box::after, .shimmer-bar::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.6) 50%, transparent 100%);
  animation: shimmer 1.8s ease-in-out infinite;
}
@keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

/* ── Animations ── */
.anim-slide-up { animation: slideUp .5s cubic-bezier(.22,1,.36,1) both; }
@keyframes slideUp {
  from { opacity: 0; transform: translateY(14px); }
  to { opacity: 1; transform: none; }
}
</style>
