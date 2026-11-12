<template>
  <div class="ph-solicitudes h-full flex flex-col gap-2 p-3 sm:p-4 overflow-hidden">

    <!-- ── Stat cards ── -->
    <div class="sol-stats-bar shrink-0">
      <StatCard
        variant="pastel" tone="info" solid-icon
        label="Solicitudes totales" :value="displayStats[0]"
        :comparacion="deltaTotal"
        :icon="ClipboardListIcon" :sparkline="ondular(serieTotal, 1)"
      />
      <StatCard
        variant="pastel" tone="warning" solid-icon
        label="Nuevas solicitudes" :value="displayStats[1]"
        :comparacion="deltaPendientes"
        :icon="ClockIcon" :sparkline="ondular(seriePendientes, 3)"
      />
      <StatCard
        variant="pastel" tone="violet" solid-icon
        label="Aceptado" :value="displayStats[2]"
        :comparacion="deltaEnEspera"
        :icon="HourglassIcon" :sparkline="ondular(serieEnEspera, 5)"
      />
      <StatCard
        variant="pastel" tone="success" solid-icon
        label="Completadas" :value="displayStats[3]"
        :comparacion="deltaCompletadas"
        :icon="CheckCircleIcon" :sparkline="ondular(serieCompletadas, 7)"
      />
    </div>

    <!-- ── Contenido: columna izquierda (chips + filtros + tabla) + panel lateral ── -->
    <div class="sol-body flex gap-2 min-h-0 overflow-hidden">
    <div class="flex-1 min-w-0 flex flex-col gap-2 min-h-0">

      <div class="sol-list-toolbar shrink-0">
        <div class="sol-list-heading">
          <h1>Solicitudes de referencia</h1>
          <span aria-live="polite">{{ solicitudesFiltradas.length }} solicitudes</span>
        </div>
        <el-input
          v-model="filtro.buscar"
          class="sol-list-search"
          placeholder="Buscar paciente o cédula"
          aria-label="Buscar solicitudes"
          :prefix-icon="SearchIcon"
          clearable
          size="small"
        />
        <div class="sol-list-tools" role="group" aria-label="Acciones del listado">
          <button type="button" :disabled="cargando" @click="cargar">
            <component :is="RefreshIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': cargando }" />
            Actualizar
          </button>
          <button type="button" :disabled="!solicitudesFiltradas.length || exportando" @click="exportarExcel">
            <component :is="DownloadIcon" class="w-3.5 h-3.5" :class="{ 'animate-pulse': exportando }" />
            {{ exportando ? 'Exportando…' : 'Exportar Excel' }}
          </button>
          <button type="button" :disabled="!solicitudesEnEspera.length || verificandoTodosIngresos" @click="verificarTodosIngresos">
            <component :is="SearchIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': verificandoTodosIngresos }" />
            {{ verificandoTodosIngresos ? 'Verificando…' : 'Verificar ingresos' }}
          </button>
        </div>
        <div ref="filtrosWrap" class="sol-filtros-wrap">
          <button class="sol-filtros-btn" :class="{ 'sol-filtros-btn-active': filtrosAbiertos || conteoFiltros > 0 }" @click="filtrosAbiertos = !filtrosAbiertos">
            <component :is="SlidersIcon" class="w-3.5 h-3.5" />
            Filtros
            <span v-if="conteoFiltros > 0" class="sol-filtros-badge">{{ conteoFiltros }}</span>
          </button>

          <!-- ── Panel de filtros ── -->
          <Transition name="bulk-slide">
            <div v-if="filtrosAbiertos" class="sol-filtros-panel">
              <DateRangeFilter v-model="rangoFechas" />
              <el-select v-model="filtroClinica" placeholder="Todas las clínicas" clearable filterable size="small" class="sol-select">
                <el-option v-for="c in clinicasFiltro" :key="c.id" :label="c.nombre" :value="c.id" />
              </el-select>
              <el-select v-model="filtroEspecialidad" placeholder="Todas las especialidades" clearable filterable size="small" class="sol-select">
                <el-option v-for="e in especialidadesFiltro" :key="e" :label="e" :value="e" />
              </el-select>
              <el-select v-model="filtroEps" placeholder="Todas las aseguradoras" clearable filterable size="small" class="sol-select">
                <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
              </el-select>
              <el-select v-model="filtroOrigen" placeholder="Todos los orígenes" clearable size="small" class="sol-select">
                <el-option label="Correo" value="correo" />
                <el-option label="Aplicativo" value="aplicativo" />
              </el-select>
              <el-select
                :model-value="tabActiva"
                placeholder="Todos los estados"
                size="small"
                class="sol-select sol-select-estado"
                @update:model-value="(v: string) => cambiarTab(v as 'todas' | 'pendiente' | 'pendiente_cama' | 'en_espera')"
              >
                <el-option label="Todos los estados" value="todas" />
                <el-option label="Nueva solicitud" value="pendiente" />
                <el-option label="Pendiente" value="pendiente_cama" />
                <el-option label="Aceptado" value="en_espera" />
              </el-select>
              <el-select v-model="sortKey" aria-label="Ordenar solicitudes por" size="small">
                <el-option label="Ordenar por fecha" value="fecha" />
                <el-option label="Ordenar por paciente" value="paciente" />
                <el-option label="Ordenar por aseguradora" value="eps" />
                <el-option label="Ordenar por clínica" value="clinica" />
                <el-option label="Ordenar por especialidad" value="especialidad" />
              </el-select>
              <el-select v-model="sortDir" aria-label="Dirección del orden" size="small">
                <el-option label="Descendente" value="desc" />
                <el-option label="Ascendente" value="asc" />
              </el-select>
              <button v-if="hayFiltros" class="sol-limpiar" @click="limpiarFiltros">Limpiar filtros</button>
            </div>
          </Transition>
        </div>
      </div>

    <!-- ── Pestañas rápidas por estado ── -->
    <div class="sol-tabs" role="tablist" aria-label="Filtrar por estado">
      <button type="button" class="sol-tab" role="tab" :class="{ 'sol-tab-active': tabActiva === 'todas' }" :aria-selected="tabActiva === 'todas'" @click="cambiarTab('todas')">
        Todas <span class="sol-tab-count">{{ resumen.total }}</span>
      </button>
      <button type="button" class="sol-tab sol-tab-pendiente" role="tab" :class="{ 'sol-tab-active': tabActiva === 'pendiente' }" :aria-selected="tabActiva === 'pendiente'" @click="cambiarTab('pendiente')">
        <span class="sol-tab-dot"></span> Nueva solicitud <span class="sol-tab-count">{{ resumen.pendientes }}</span>
      </button>
      <button type="button" class="sol-tab sol-tab-pendiente-cama" role="tab" :class="{ 'sol-tab-active': tabActiva === 'pendiente_cama' }" :aria-selected="tabActiva === 'pendiente_cama'" @click="cambiarTab('pendiente_cama')">
        <span class="sol-tab-dot"></span> Pendiente <span class="sol-tab-count">{{ resumen.pendientesCama }}</span>
      </button>
      <button type="button" class="sol-tab sol-tab-en-espera" role="tab" :class="{ 'sol-tab-active': tabActiva === 'en_espera' }" :aria-selected="tabActiva === 'en_espera'" @click="cambiarTab('en_espera')">
        <span class="sol-tab-dot"></span> Aceptado <span class="sol-tab-count">{{ resumen.enEspera }}</span>
      </button>
    </div>

    <!-- ── Listado de referencias ── -->
    <div class="flex-1 min-h-0 overflow-hidden sol-list-panel">
      <!-- Loading -->
      <div v-if="cargando" class="sol-table-loading">
        <div v-for="i in 6" :key="i" class="sol-table-row-skeleton">
          <div class="shimmer-box" style="width:18px; height:18px; border-radius:4px; flex-shrink:0;"></div>
          <div class="shimmer-box" style="width:32px; height:32px; border-radius:8px; flex-shrink:0;"></div>
          <div class="flex-1 space-y-1.5">
            <div class="shimmer-bar" style="width:35%; height:13px;"></div>
            <div class="shimmer-bar" style="width:22%; height:10px;"></div>
          </div>
          <div class="shimmer-bar" style="width:12%; height:11px;"></div>
          <div class="shimmer-bar" style="width:10%; height:11px;"></div>
          <div class="shimmer-box" style="width:65px; height:22px; border-radius:999px;"></div>
          <div class="shimmer-box" style="width:90px; height:26px; border-radius:6px; flex-shrink:0;"></div>
        </div>
      </div>

      <!-- Vacío -->
      <div v-else-if="solicitudesPaginadas.length === 0" class="sol-empty-wrap">
        <div class="sol-empty-icon w-14 h-14 rounded-2xl flex items-center justify-center mb-3">
          <component :is="FileTextIcon" class="w-7 h-7" />
        </div>
        <p class="font-bold text-sm mb-1" style="color:#0d2d5e;">Sin solicitudes</p>
        <p class="text-xs max-w-[260px]" style="color:#64748b;">No se encontraron solicitudes en esta categoría.</p>
      </div>

      <!-- Tarjetas compactas -->
      <div v-else class="flex flex-col h-full overflow-hidden">
        <div class="sol-reference-scroll custom-scrollbar">
            <TransitionGroup name="sol-row" tag="div" class="sol-reference-list" role="list" aria-label="Solicitudes de referencia">
              <article
                v-for="s in solicitudesPaginadas"
                :id="`sol-row-${s.id}`"
                :key="s.id"
                role="listitem"
                class="sol-reference-card"
                :class="[
                  { 'sol-reference-highlighted': resaltarId === s.id, 'sol-reference-urgent': s.urgencia_vital },
                  !s.urgencia_vital ? `sol-reference-card--${s.estado}` : ''
                ]"
                :aria-label="`${idInterno(s.id)} · ${nombreCompleto(s)}`"
              >
                  <div class="sol-reference-patient">
                    <div class="sol-reference-avatar" :style="avatarStyle(s)">
                      {{ inicialesPaciente(s) }}
                      <span v-if="s.inbox_mensaje_id" class="sol-correo-badge" title="Registrada desde un correo de la Bandeja">
                        <component :is="MailIcon" />
                      </span>
                    </div>
                    <div class="sol-reference-identity">
                      <button type="button" class="sol-reference-name" :title="nombreCompleto(s)" @click="verDetalle(s)">{{ nombreCompleto(s) }}</button>
                      <p class="sol-reference-document">{{ s.edad }} años · {{ s.genero }} · {{ s.tipo_documento }} {{ s.numero_documento }}</p>
                      <div class="sol-reference-insurer">
                        <span class="sol-reference-insurer-label"><component :is="ShieldIcon" /> Aseguradora</span>
                        <span class="sol-reference-insurer-chip" :title="s.eps">{{ s.eps }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="sol-reference-clinic sol-reference-divider">
                    <span class="sol-reference-label">Clínica emisora</span>
                    <p class="sol-reference-clinic-name" :title="s.clinica?.nombre ?? s.institucion_remitente">{{ s.clinica?.nombre ?? s.institucion_remitente ?? '—' }}</p>
                    <p class="sol-reference-contact" v-if="s.quien_remitente || s.telefono_contacto" :title="[s.quien_remitente, s.telefono_contacto].filter(Boolean).join(' · ')">
                    {{ s.quien_remitente || '—' }}<span v-if="s.telefono_contacto"> · {{ s.telefono_contacto }}</span>
                    </p>
                    <span class="sol-reference-label sol-reference-diagnosis-label">Servicio que refiere</span>
                    <p class="sol-reference-diagnosis" :title="s.servicio_remision || s.servicio_ubicacion_actual">{{ s.servicio_remision || s.servicio_ubicacion_actual || '—' }}</p>
                  </div>
                  <div class="sol-reference-clinical sol-reference-divider">
                    <span class="sol-reference-label">Especialidad</span>
                    <p class="sol-reference-specialty" :title="s.especialidad_requerida">{{ s.especialidad_requerida }}</p>
                    <span class="sol-reference-label sol-reference-diagnosis-label">Diagnóstico</span>
                    <p v-if="s.diagnosticos?.length" class="sol-reference-diagnosis" :title="descripcionSinCodigo(s.diagnosticos[0])">
                    {{ descripcionSinCodigo(s.diagnosticos[0]) }}<span v-if="s.diagnosticos.length > 1" class="sol-dx-more"> +{{ s.diagnosticos.length - 1 }}</span>
                    </p>
                    <p v-else class="sol-reference-diagnosis" :title="s.diagnostico">{{ s.diagnostico || '—' }}</p>
                  </div>
                  <div class="sol-reference-state sol-reference-divider">
                    <div class="sol-origen" :class="s.inbox_mensaje_id ? 'sol-origen--correo' : 'sol-origen--app'" :title="s.inbox_mensaje_id ? 'Registrada desde un correo de la Bandeja' : 'Registrada desde el portal de clínicas'">
                      <span class="sol-reference-label">Origen</span>
                      <span class="sol-origen-val">
                        <component :is="s.inbox_mensaje_id ? MailIcon : MonitorSmartphoneIcon" />
                        {{ s.inbox_mensaje_id ? 'Correo electrónico' : 'Aplicativo' }}
                      </span>
                    </div>
                    <span v-if="s.urgencia_vital" class="sol-reference-status sol-reference-vital">
                      <span class="sol-status-dot"></span>Urgencia vital
                    </span>
                    <span v-else class="sol-reference-status" :class="`sol-reference-status--${s.estado}`" :title="estadoTooltip(s.estado)">
                      <span class="sol-status-dot"></span>{{ estadoLabel(s.estado) }}
                    </span>
                    <p class="sol-reference-date">{{ formatFecha(s.fecha) }} · {{ formatHora(s.hora) }}</p>
                    <div class="sol-reference-age">
                      <span class="sol-edad" :class="edadRelativa(s).clase">{{ edadRelativa(s).texto }}</span>
                      <span v-if="s.urgencia_vital" class="sol-reference-status" :class="`sol-reference-status--${s.estado}`">{{ estadoLabel(s.estado) }}</span>
                    </div>
                  </div>
                  <div class="sol-table-actions sol-reference-actions">
                    <el-button circle size="small" title="Ver detalle" aria-label="Ver detalle de la solicitud" @click="verDetalle(s)">
                      <component :is="EyeIcon" class="w-3.5 h-3.5" />
                    </el-button>
                    <span v-if="tieneEvoluciones(s)" class="sol-evo-wrap">
                      <el-button
                        circle
                        size="small"
                        class="sol-evoluciones-btn"
                        :title="`Ver evoluciones (${contarEvoluciones(s)})`"
                        aria-label="Ver evoluciones del paciente"
                        @click="verEvoluciones(s)"
                      >
                        <component :is="ActivityIcon" class="w-3.5 h-3.5" />
                      </el-button>
                      <span class="sol-evo-count">{{ contarEvoluciones(s) }}</span>
                    </span>
                    <el-dropdown v-if="s.estado !== 'completado'" trigger="click" placement="bottom-end" :popper-options="{ modifiers: [{ name: 'offset', options: { offset: [8, 8] } }] }" popper-class="sol-acciones-menu" @command="(cmd: string) => manejarAccionFila(cmd, s)">
                      <el-button circle size="small" title="Más acciones" aria-label="Más acciones de la solicitud" :loading="enviandoGomedisysId === s.id || consultandoIngresoId === s.id">
                        <component :is="MoreIcon" class="w-3.5 h-3.5" />
                      </el-button>
                      <template #dropdown>
                        <el-dropdown-menu>
                          <el-dropdown-item v-if="s.estado === 'pendiente'" command="aceptar" :icon="CheckIcon" class="sol-accion-success">Aceptar</el-dropdown-item>
                          <el-dropdown-item v-if="s.estado === 'pendiente'" command="negar" :icon="XIcon" class="sol-accion-danger" divided>Negar</el-dropdown-item>
                          <el-dropdown-item v-if="s.estado === 'pendiente_cama'" command="aceptar" :icon="CheckIcon" class="sol-accion-success">Asignar cupo y enviar código</el-dropdown-item>
                          <el-dropdown-item v-if="s.estado === 'en_espera'" command="ingreso" :icon="SearchIcon" class="sol-accion-teal">Consultar ingreso</el-dropdown-item>
                          <el-dropdown-item v-if="s.estado === 'pendiente_cama'" command="evolucion" :icon="SendIcon" class="sol-accion-teal" divided>Solicitar evolución por correo</el-dropdown-item>
                          <el-dropdown-item v-if="s.estado === 'pendiente_cama' || s.estado === 'en_espera'" command="nota" :icon="StickyNoteIcon" class="sol-accion-amber">Agregar nota</el-dropdown-item>
                          <el-dropdown-item v-if="s.estado !== 'pendiente'" command="cambiar" :icon="RotateCcwIcon" class="sol-accion-violet">Cambiar estado</el-dropdown-item>
                        </el-dropdown-menu>
                      </template>
                    </el-dropdown>
                  </div>
              </article>
            </TransitionGroup>
        </div>
        <!-- Pagination -->
        <div class="sol-pagination">
          <span class="sol-pagination-info">
            Mostrando {{ (paginaActual - 1) * itemsPorPagina + 1 }}-{{ Math.min(paginaActual * itemsPorPagina, solicitudesFiltradas.length) }}
            de {{ solicitudesFiltradas.length }} solicitudes
          </span>
          <div class="sol-pagination-controls">
            <button class="sol-pagination-btn" :disabled="paginaActual === 1" @click="paginaActual--">
              <component :is="ChevronLeftIcon" class="w-3.5 h-3.5" />
            </button>
            <template v-for="(p, idx) in paginasVisibles" :key="idx">
              <button
                v-if="p !== '…'"
                class="sol-pagination-btn sol-page-num"
                :class="{ 'sol-page-active': p === paginaActual }"
                @click="paginaActual = p"
              >{{ p }}</button>
              <span v-else class="sol-page-ellipsis">…</span>
            </template>
            <button class="sol-pagination-btn" :disabled="paginaActual === totalPaginas" @click="paginaActual++">
              <component :is="ChevronRightIcon" class="w-3.5 h-3.5" />
            </button>
          </div>
          <el-select v-model="itemsPorPagina" size="small" class="sol-per-page">
            <el-option v-for="n in [10, 15, 25, 50]" :key="n" :label="`${n} por página`" :value="n" />
          </el-select>
        </div>
      </div>
    </div>
    </div>

    <!-- ── Panel lateral derecho: salud de la cola (solo con permiso de analíticas) ── -->
    <aside v-if="puedeVerAnalitica" class="sol-side">
      <!-- Gauge: salud de la cola -->
      <div class="sol-side-panel">
        <div class="sol-side-head">
          <component :is="GaugeIcon" class="w-3.5 h-3.5 sol-side-icon" />
          <p class="sol-side-title">Salud de la cola</p>
          <PanelHelp alinear="derecha" texto="Cómo va la cola de solicitudes: cuántas llevan menos de 24h (verde = al día) y cuántas están viejas — más de 7 días es urgente. 'Sin gestionar' son las que nadie ha revisado." />
          <span class="sol-side-pill sol-side-pill-live"><span class="sol-live-dot"></span>En vivo</span>
        </div>
        <!-- Resumen + barra segmentada por antigüedad de la cola -->
        <div class="cola-resumen">
          <span class="cola-resumen-num">{{ solicitudes.length }}</span>
          <div class="cola-resumen-txt">
            <span class="cola-resumen-label">activas en cola</span>
            <span class="cola-resumen-badge" :class="saludColaPct >= 50 ? 'cola-resumen-badge--ok' : 'cola-resumen-badge--warn'">
              {{ saludColaPct }}% &lt;24h
            </span>
          </div>
        </div>
        <div class="cola-bar">
          <div
            v-for="seg in colaTramos" :key="seg.id"
            class="cola-seg" :class="{ 'cola-seg--on': edadCola === seg.id }"
            :style="{ width: seg.pct + '%', background: seg.grad }"
            :title="`${seg.label}: ${seg.total} solicitudes`"
            @click="verTramo(seg.id)"
          ></div>
        </div>
        <div class="cola-rows">
          <button
            v-for="seg in colaTramos" :key="seg.id"
            type="button" class="cola-row" :class="{ 'cola-row--on': edadCola === seg.id }"
            @click="verTramo(seg.id)"
          >
            <span class="cola-row-dot" :style="{ background: seg.color }"></span>
            <span class="cola-row-label">{{ seg.label }}</span>
            <span class="cola-row-track"><i :style="{ width: seg.pct + '%', background: seg.grad }"></i></span>
            <span class="cola-row-num">{{ seg.total }}</span>
          </button>
        </div>
        <div class="sol-gauge-stats">
          <div class="sol-gauge-stat sol-gauge-stat--link" title="Ver la solicitud más antigua" @click="verMasAntigua">
            <span class="sol-gauge-stat-num">{{ masAntiguaTxt }}</span>
            <span class="sol-gauge-stat-label">Más antigua</span>
          </div>
          <div class="sol-gauge-stat sol-gauge-stat--link" title="Ver solicitudes con más de 24h en cola" @click="verCriticas">
            <span class="sol-gauge-stat-num" :class="{ 'sol-gauge-stat-warn': colaCriticas > 0 }">{{ colaCriticas }}</span>
            <span class="sol-gauge-stat-label">&gt; 24h</span>
          </div>
          <div class="sol-gauge-stat sol-gauge-stat--link" title="Ver solicitudes pendientes sin gestionar" @click="verSinGestionar">
            <span class="sol-gauge-stat-num">{{ resumen.pendientes }}</span>
            <span class="sol-gauge-stat-label">Sin gestionar</span>
          </div>
        </div>
      </div>

      <!-- Especialidades en cola -->
      <div class="sol-side-panel sol-side-panel-flex">
        <div class="sol-side-head">
          <component :is="ActivityIcon" class="w-3.5 h-3.5 sol-side-icon" />
          <p class="sol-side-title">Especialidades en cola</p>
          <PanelHelp alinear="derecha" texto="Las especialidades con más solicitudes esperando en la cola — sirve para ver dónde se concentra la demanda pendiente." />
          <span class="sol-side-pill">Más requeridas</span>
        </div>
        <div class="sol-esp-list">
          <div v-for="(e, i) in topEspecialidadesCola" :key="e.nombre" class="sol-esp-row">
            <span class="sol-esp-rank" :class="`sol-esp-rank--${i + 1}`">{{ i + 1 }}</span>
            <div class="sol-esp-info">
              <p class="sol-esp-name">{{ e.nombre }}</p>
              <div class="sol-esp-bar">
                <div class="sol-esp-fill" :style="{ width: e.pct + '%', background: espColaColors[i % espColaColors.length] }"></div>
              </div>
            </div>
            <span class="sol-esp-total">{{ e.total }}</span>
          </div>
          <p v-if="!topEspecialidadesCola.length" class="sol-esp-empty">Sin solicitudes activas</p>
        </div>
      </div>
    </aside>
    </div>

    <!-- ── Modal: Detalle ── -->
    <el-dialog v-model="modalDetalle" width="1140px" class="detalle-dialog" :show-close="false" align-center @close="limpiarThumbnails">
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="cerrarDetalle">
            <component :is="XIcon" class="w-4 h-4" />
          </button>

          <!-- Header claro -->
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon">
              <component :is="ClipboardListIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Detalle de solicitud</p>
              <p class="detalle-head-sub">{{ idInterno(solicitudSeleccionada.id) }} · {{ formatFecha(solicitudSeleccionada.fecha) }} · {{ formatHora(solicitudSeleccionada.hora) }}</p>
            </div>
            <span class="detalle-head-badge" :class="'estado-' + solicitudSeleccionada.estado" :title="estadoTooltip(solicitudSeleccionada.estado)">
              {{ estadoLabel(solicitudSeleccionada.estado) }}
            </span>
            <span v-if="solicitudSeleccionada.urgencia_vital" class="sol-urgencia-detail-badge">
              URGENCIA VITAL
            </span>
            <button class="detalle-head-pdf" @click="exportarPdf">
              <component :is="FileDownIcon" class="w-3.5 h-3.5" />
              <span>Descargar PDF</span>
            </button>
          </div>

          <div class="detalle-body">
            <!-- Paciente + Remisión + Diagnósticos en 3 columnas -->
            <div class="grid grid-cols-3 gap-2 mb-1.5">
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-blue"><component :is="UserIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-blue">Paciente</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Nombre</span><strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong></div>
                  <div class="data-row"><span>Documento</span><strong>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }}</strong></div>
                  <div class="data-row"><span>Edad / Género</span><strong>{{ solicitudSeleccionada.edad }} años · {{ solicitudSeleccionada.genero === 'M' ? 'Masc.' : 'Fem.' }}</strong></div>
                  <div class="data-row"><span>EPS</span><strong>{{ solicitudSeleccionada.eps }}</strong></div>
                  <div class="data-row"><span>Municipio</span><strong>{{ solicitudSeleccionada.municipio_capita }}</strong></div>
                  <div class="data-row"><span>Especialidad</span><strong>{{ solicitudSeleccionada.especialidad_requerida }}</strong></div>
                  <div v-if="solicitudSeleccionada.tipo_referencia" class="data-row"><span>Tipo de referencia</span><strong>{{ solicitudSeleccionada.tipo_referencia }}</strong></div>
                  <div class="data-row"><span>Servicio actual</span><strong>{{ solicitudSeleccionada.servicio_ubicacion_actual }}</strong></div>
                </div>
              </div>
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-amber"><component :is="BuildingIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-amber">Remisión</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Fecha solicitud</span><strong>{{ formatFecha(solicitudSeleccionada.fecha) }} · {{ formatHora(solicitudSeleccionada.hora) }}</strong></div>
                  <div class="data-row"><span>Institución</span><strong>{{ solicitudSeleccionada.clinica?.nombre ?? solicitudSeleccionada.institucion_remitente ?? '—' }}</strong></div>
                  <div v-if="solicitudSeleccionada.quien_remitente" class="data-row"><span>Remite</span><strong>{{ solicitudSeleccionada.quien_remitente }}</strong></div>
                  <div v-if="solicitudSeleccionada.telefono_contacto" class="data-row"><span>Teléfono</span><strong>{{ solicitudSeleccionada.telefono_contacto }}</strong></div>
                  <div v-if="solicitudSeleccionada.correo_contacto" class="data-row"><span>Correo</span><strong>{{ solicitudSeleccionada.correo_contacto }}</strong></div>
                  <div v-if="solicitudSeleccionada.codigo_aceptacion" class="data-row"><span>Código Gomedisys</span><strong class="font-mono">{{ solicitudSeleccionada.codigo_aceptacion }}</strong></div>
                  <div v-if="solicitudSeleccionada.estado === 'pendiente_cama' && solicitudSeleccionada.lugar_llegada" class="data-row"><span>Lugar de referencia</span><strong>{{ solicitudSeleccionada.lugar_llegada }} <small class="text-gray-400">(se envía con el código)</small></strong></div>
                  <div v-if="solicitudSeleccionada.hora_llegada" class="data-row"><span>Llegada esperada</span><strong>{{ formatHora(solicitudSeleccionada.hora_llegada) }} · {{ solicitudSeleccionada.lugar_llegada }}<template v-if="solicitudSeleccionada.sede"> · Sede {{ solicitudSeleccionada.sede }}</template></strong></div>
                  <p v-if="!solicitudSeleccionada.quien_remitente && !solicitudSeleccionada.telefono_contacto && !solicitudSeleccionada.correo_contacto" class="text-xs text-gray-400 italic">Sin datos adicionales de remisión</p>
                </div>
              </div>
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-violet"><component :is="ClipboardListIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-violet">Diagnósticos</p>
                </div>
                <div class="card-body">
                  <div v-if="solicitudSeleccionada.diagnosticos?.length" class="card-dx-list">
                    <div v-for="dx in solicitudSeleccionada.diagnosticos" :key="dx.id" class="card-dx-item">
                      <strong class="card-dx-code">{{ dx.codigo_cie10 }}</strong>
                      <span class="card-dx-desc">{{ descripcionSinCodigo(dx) }}</span>
                    </div>
                  </div>
                  <p v-else-if="solicitudSeleccionada.diagnostico" class="card-text">{{ solicitudSeleccionada.diagnostico }}</p>
                  <p v-else class="card-text">—</p>
                </div>
              </div>
            </div>

            <!-- Historia clínica grande a la izquierda; seguimiento y
                 soportes apilados a la derecha -->
            <div class="detalle-layout">
              <div class="detalle-card detalle-hc-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-blue"><component :is="ClipboardListIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-blue">Historia clínica</p>
                </div>
                <div class="card-body detalle-hc-body">
                  <div class="detalle-hc-box">
                    <p class="card-text-sm">{{ solicitudSeleccionada.resumen_historia_clinica }}</p>
                  </div>
                </div>
              </div>
              <div class="detalle-layout-side">
                <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-slate"><component :is="ClockIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-slate">Seguimiento</p>
                  <button
                    v-if="solicitudSeleccionada.estado === 'pendiente_cama' || solicitudSeleccionada.estado === 'en_espera'"
                    type="button"
                    class="nota-add-btn"
                    @click="abrirNota(solicitudSeleccionada)"
                  >
                    <component :is="StickyNoteIcon" class="w-3 h-3" /> Nota
                  </button>
                </div>
                <div class="card-body">
                  <div v-for="(paso, i) in pasosSeguimiento" :key="i" class="timeline-item">
                    <div class="timeline-connector">
                      <div class="timeline-dot" :class="{ 'timeline-dot-done': paso.hecho, 'timeline-dot-negada': paso.tipo === 'negada', 'timeline-dot-revertido': paso.tipo === 'revertido' }">
                        <component :is="ICONO_PASO[paso.tipo]" class="w-2.5 h-2.5" />
                      </div>
                      <div v-if="i < pasosSeguimiento.length - 1" class="timeline-line" :class="{ 'timeline-line-done': paso.hecho }"></div>
                    </div>
                    <div class="timeline-text">
                      <p class="timeline-titulo" :class="{ 'timeline-titulo-pending': !paso.hecho }">{{ paso.titulo }}</p>
                      <p class="timeline-fecha">{{ paso.fecha }}</p>
                    </div>
                  </div>
                  <div v-if="solicitudSeleccionada.observaciones_respuesta" class="notas-list">
                    <p class="notas-list-title">Observaciones de la respuesta</p>
                    <div class="nota-item">
                      <div class="nota-item-head">
                        <component :is="StickyNoteIcon" class="w-3 h-3 nota-item-icon" />
                        <span class="nota-item-autor">{{ solicitudSeleccionada.nombre_quien_responde || 'Referencia' }}</span>
                        <span v-if="solicitudSeleccionada.hora_respuesta" class="nota-item-fecha">{{ formatHora(solicitudSeleccionada.hora_respuesta) }}</span>
                      </div>
                      <p class="nota-item-texto">{{ solicitudSeleccionada.observaciones_respuesta }}</p>
                    </div>
                  </div>
                  <p v-if="solicitudSeleccionada.motivo_negacion" class="timeline-obs timeline-obs-negada"><template v-if="grupoNegacionLabel(solicitudSeleccionada.grupo_negacion)"><strong>{{ grupoNegacionLabel(solicitudSeleccionada.grupo_negacion) }}</strong> — </template>{{ solicitudSeleccionada.motivo_negacion }}</p>
                  <div v-if="notasSeguimiento.length" class="notas-list">
                    <p class="notas-list-title">Notas de seguimiento</p>
                    <div v-for="nota in notasSeguimiento" :key="nota.id" class="nota-item">
                      <div class="nota-item-head">
                        <component :is="StickyNoteIcon" class="w-3 h-3 nota-item-icon" />
                        <span class="nota-item-autor">{{ nota.titulo.replace('Nota de seguimiento — ', '') }}</span>
                        <span class="nota-item-fecha">{{ formatFecha(nota.created_at) }} · {{ horaDeFecha(nota.created_at) }}</span>
                      </div>
                      <p class="nota-item-texto">{{ nota.descripcion }}</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-rose"><component :is="PaperclipIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-rose">Soportes</p>
                </div>
                <div class="card-body">
                  <div v-if="solicitudSeleccionada.adjuntos?.length" class="adjunto-list">
                    <button
                      v-for="adj in solicitudSeleccionada.adjuntos"
                      :key="adj.id"
                      type="button"
                      class="adjunto-row"
                      @click="abrirAdjunto(adj)"
                    >
                      <img v-if="thumbnails.has(adj.id)" :src="thumbnails.get(adj.id)" class="adjunto-thumb" :alt="adj.nombre_original" />
                      <span v-else class="adjunto-icon" :class="isPdf(adj) ? 'adjunto-pdf' : 'adjunto-img'">{{ isPdf(adj) ? 'PDF' : esImagenMime(adj.mime_type) ? 'IMG' : 'DOC' }}</span>
                      <div class="adjunto-info">
                        <p class="adjunto-name">{{ adj.nombre_original }}</p>
                        <p class="adjunto-meta">{{ formatFileSize(adj.tamano) }}</p>
                      </div>
                      <component :is="isPreviewable(adj) ? EyeIcon : DownloadIcon" class="w-3.5 h-3.5 adjunto-action-icon" />
                    </button>
                  </div>
                  <p v-else class="adjunto-empty">Sin archivos adjuntos</p>
                </div>
              </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="detalle-footer">
            <p class="detalle-footer-note">
              <component :is="ShieldIcon" class="w-3.5 h-3.5" />
              La información está protegida y será tratada confidencialmente.
            </p>
            <button type="button" class="detalle-cerrar-btn" @click="cerrarDetalle">
              <component :is="SendIcon" class="w-3.5 h-3.5" />
              Cerrar
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="modalEvoluciones" width="min(720px, 94vw)" class="evoluciones-dialog" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="evoluciones-modal">
          <header class="evoluciones-modal-head">
            <div class="evoluciones-modal-icon"><component :is="ActivityIcon" class="w-5 h-5" /></div>
            <div class="evoluciones-modal-heading">
              <h2>Evolución del paciente</h2>
              <p>{{ nombreCompleto(solicitudSeleccionada) }} · {{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }}</p>
            </div>
            <span class="evoluciones-total">{{ evolucionesPaciente.length }} {{ evolucionesPaciente.length === 1 ? 'registro' : 'registros' }}</span>
            <button type="button" class="evoluciones-modal-close" aria-label="Cerrar evoluciones" @click="modalEvoluciones = false">
              <component :is="XIcon" class="w-4 h-4" />
            </button>
          </header>

          <div class="evoluciones-modal-body">
            <div v-if="evolucionesPaciente.length" class="evoluciones-modal-list">
              <article v-for="evolucion in evolucionesPaciente" :key="evolucion.id" class="evoluciones-modal-item">
                <div class="evoluciones-modal-rail"><span></span></div>
                <div class="evoluciones-modal-entry">
                  <div class="evoluciones-modal-meta">
                    <div>
                      <strong>{{ solicitudSeleccionada.clinica?.nombre ?? solicitudSeleccionada.institucion_remitente ?? 'Clínica remitente' }}</strong>
                      <time>{{ formatFecha(evolucion.created_at) }} · {{ horaDeFecha(evolucion.created_at) }}</time>
                    </div>
                    <span class="evoluciones-entry-tag">Evolución</span>
                  </div>
                  <p>{{ evolucion.texto || 'La clínica registró una evolución sin descripción.' }}</p>
                  <div v-if="evolucion.adjuntos?.length" class="evoluciones-adjuntos">
                    <a
                      v-for="adj in evolucion.adjuntos"
                      :key="adj.id"
                      :href="adjuntoUrl(adj)"
                      target="_blank"
                      rel="noopener"
                      class="evoluciones-adjunto"
                      :title="`Abrir ${adj.nombre_original}`"
                    >
                      <component :is="PaperclipIcon" class="w-3 h-3" />
                      <span class="evoluciones-adjunto-name">{{ adj.nombre_original }}</span>
                      <small>{{ formatTamano(adj.tamano) }}</small>
                    </a>
                  </div>

                  <div v-for="r in evolucion.respuestas" :key="r.id" class="evoluciones-respuesta">
                    <div class="evoluciones-respuesta-head">
                      <component :is="MessageIcon" class="w-3.5 h-3.5" />
                      <strong>{{ r.respondido_por || 'Referencia' }}</strong>
                      <time>{{ formatFecha(r.created_at) }} · {{ horaDeFecha(r.created_at) }}</time>
                      <span v-if="r.correo_enviado" class="evoluciones-respuesta-mail" title="Se envió por correo a la clínica">
                        <component :is="MailIcon" class="w-3 h-3" /> Enviada por correo
                      </span>
                    </div>
                    <p>{{ r.mensaje }}</p>
                  </div>

                  <div v-if="evolucion.responder" class="evoluciones-responder">
                    <textarea
                      v-model="respuestaDraft[evolucion.id]"
                      rows="2"
                      maxlength="2000"
                      class="evoluciones-responder-input"
                      placeholder="Escriba su respuesta a esta evolución. Se envía por correo a la clínica y queda en el módulo de Evolución…"
                    ></textarea>
                    <button
                      type="button"
                      class="evoluciones-responder-btn"
                      :disabled="!(respuestaDraft[evolucion.id] ?? '').trim() || respondiendoId !== null"
                      @click="responderEvolucion(evolucion.id)"
                    >
                      <component :is="SendIcon" class="w-3.5 h-3.5" />
                      {{ respondiendoId === evolucion.id ? 'Enviando…' : 'Responder' }}
                    </button>
                  </div>
                </div>
              </article>
            </div>
            <div v-else class="evoluciones-empty">
              <component :is="ActivityIcon" class="w-7 h-7" />
              <strong>Sin evoluciones registradas</strong>
              <span>La clínica todavía no ha añadido notas de evolución para este paciente.</span>
            </div>
          </div>

          <footer class="evoluciones-modal-footer">
            <span><component :is="ShieldIcon" class="w-3.5 h-3.5" /> Información clínica confidencial</span>
            <button type="button" class="evoluciones-modal-done" @click="modalEvoluciones = false">Cerrar</button>
          </footer>
        </div>
      </template>
    </el-dialog>

    <!-- ── Modal: Visor de adjunto ── -->
    <el-dialog v-model="modalPdf" width="720px" :class="['pdf-dialog', { 'pdf-fullscreen': pantallaCompleta }]" align-center :show-close="true">
      <template #header>
        <div class="pdf-dialog-header">
          <component :is="isImageAdjunto ? ImageIcon : FileTextIcon" class="w-4 h-4 flex-shrink-0" />
          <p class="pdf-dialog-title">{{ adjuntoActivo?.nombre_original ?? 'Documento' }}</p>
          <a
            v-if="adjuntoActivo"
            :href="pdfBlobUrl || adjuntoUrl(adjuntoActivo)"
            :download="adjuntoActivo.nombre_original"
            class="pdf-dialog-download"
          >
            <component :is="DownloadIcon" class="w-3.5 h-3.5" />
            Descargar
          </a>
        </div>
      </template>
      <div
        class="pdf-viewer-wrap"
        :class="{ 'pdf-viewer-fullscreen': pantallaCompleta }"
        @wheel.prevent="onWheel"
      >
        <!-- Zoom toolbar -->
        <div v-if="!cargandoPdf && adjuntoActivo && isPreviewable(adjuntoActivo)" class="pdf-zoom-toolbar">
          <button class="pdf-zoom-btn" @click="zoomOut" :disabled="zoomLevel <= 0.25" title="Alejar">
            <component :is="ZoomOutIcon" class="w-4 h-4" />
          </button>
          <span class="pdf-zoom-label">{{ Math.round(zoomLevel * 100) }}%</span>
          <button class="pdf-zoom-btn" @click="zoomIn" :disabled="zoomLevel >= 4" title="Acercar">
            <component :is="ZoomInIcon" class="w-4 h-4" />
          </button>
          <button class="pdf-zoom-btn" @click="zoomReset" title="Restablecer">
            <component :is="MaximizeIcon" class="w-4 h-4" />
          </button>
          <button class="pdf-zoom-btn" @click="togglePantallaCompleta" :title="pantallaCompleta ? 'Salir pantalla completa' : 'Pantalla completa'">
            <component :is="pantallaCompleta ? MinimizeIcon : ExpandIcon" class="w-4 h-4" />
          </button>
        </div>
        <div v-if="cargandoPdf" class="pdf-viewer-loading">
          <component :is="RefreshIcon" class="w-6 h-6 animate-spin text-blue-500" />
          <p>Cargando documento...</p>
        </div>
        <iframe
          v-else-if="adjuntoActivo && pdfBlobUrl && isPdf(adjuntoActivo)"
          :src="pdfZoomUrl"
          class="pdf-viewer-iframe"
          frameborder="0"
        ></iframe>
        <div v-else-if="adjuntoActivo && pdfBlobUrl && isImage(adjuntoActivo)" class="pdf-viewer-image-wrap">
          <img :src="pdfBlobUrl" :alt="adjuntoActivo.nombre_original" class="pdf-viewer-image" :style="{ transform: `scale(${zoomLevel})` }" />
        </div>
        <div v-else-if="adjuntoActivo && !isPreviewable(adjuntoActivo)" class="pdf-viewer-fallback">
          <component :is="FileTextIcon" class="w-10 h-10 text-gray-300" />
          <p>Este archivo no se puede previsualizar</p>
          <a :href="adjuntoUrl(adjuntoActivo)" download class="pdf-viewer-download-btn">
            <component :is="DownloadIcon" class="w-4 h-4" />
            Descargar archivo
          </a>
        </div>
      </div>
    </el-dialog>

    <!-- ── Modal: Solicitar evolución por correo ── -->
    <el-dialog v-model="modalSolicitarEvolucion" width="560px" class="accion-dialog" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="modalSolicitarEvolucion = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon accion-icon-info">
              <component :is="SendIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Solicitar evolución</p>
              <p class="detalle-head-sub">{{ idInterno(solicitudSeleccionada.id) }} · se envía por correo a la clínica</p>
            </div>
          </div>

          <div class="detalle-body">
            <div class="accion-patient-card">
              <div class="accion-patient-avatar">{{ inicialesPaciente(solicitudSeleccionada) }}</div>
              <div class="accion-patient-info">
                <strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong>
                <span>{{ solicitudSeleccionada.clinica?.nombre ?? solicitudSeleccionada.institucion_remitente ?? 'Clínica remitente' }} · {{ solicitudSeleccionada.especialidad_requerida }}</span>
              </div>
            </div>

            <div class="detalle-card">
              <div class="card-body accion-form-body">
                <div class="mb-1.5">
                  <label class="accion-field-label">Mensaje para la clínica</label>
                  <el-input
                    v-model="formEvolucion.mensaje"
                    type="textarea"
                    :rows="4"
                    maxlength="1000"
                    show-word-limit
                    placeholder="Escriba aquí lo que necesita que la clínica conozca o envíe (opcional). Irá en el correo junto al enlace al módulo de Evolución."
                  />
                </div>
                <div class="mb-1.5">
                  <label class="accion-field-label">Correo de notificación a la clínica</label>
                  <div class="correo-destino-row">
                    <span class="correo-destino-chip" :title="correoDestinoClinica">{{ correoDestinoClinica }}</span>
                    <el-select v-model="formEvolucion.correos_extra" multiple filterable allow-create default-first-option :reserve-keyword="false" collapse-tags :max-collapse-tags="2" placeholder="Agregar otro correo y presionar Enter (opcional)" class="flex-1" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="detalle-footer">
            <button type="button" class="accion-cancelar-btn" @click="modalSolicitarEvolucion = false">Cancelar</button>
            <button type="button" class="accion-confirmar-btn accion-confirmar-info" :disabled="enviandoEvolucion" @click="enviarSolicitudEvolucion">
              <component :is="enviandoEvolucion ? Loader2Icon : SendIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': enviandoEvolucion }" />
              Enviar solicitud
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- ── Modal: Aceptar ── -->
    <el-dialog v-model="modalAceptar" width="680px" class="accion-dialog" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="modalAceptar = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon accion-icon-success">
              <component :is="CheckIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Aceptar solicitud</p>
              <p class="detalle-head-sub">{{ idInterno(solicitudSeleccionada.id) }} · el paciente quedará en espera de llegada</p>
            </div>
          </div>

          <div class="detalle-body">
            <div class="accion-patient-card">
              <div class="accion-patient-avatar">{{ inicialesPaciente(solicitudSeleccionada) }}</div>
              <div class="accion-patient-info">
                <strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong>
                <span>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }} · {{ solicitudSeleccionada.edad }} años · {{ solicitudSeleccionada.especialidad_requerida }}</span>
              </div>
            </div>

            <div class="detalle-card">
              <div class="card-body accion-form-body">
                <div class="grid grid-cols-3 gap-2 mb-1.5">
                  <div>
                    <label class="accion-field-label">Fecha</label>
                    <el-input :model-value="formAceptar.fecha_respuesta" disabled />
                  </div>
                  <div>
                    <label class="accion-field-label">Hora</label>
                    <el-input :model-value="formAceptar.hora_respuesta" disabled />
                  </div>
                  <div>
                    <label class="accion-field-label">Quien responde</label>
                    <el-input :model-value="formAceptar.nombre_quien_responde" disabled />
                  </div>
                </div>
                <!-- Si ya quedó en espera de cupo, al reabrir se asigna el cupo
                     directo — la pregunta solo aplica a solicitudes nuevas. -->
                <div v-if="solicitudSeleccionada.estado !== 'pendiente_cama'" class="mb-1.5">
                  <label class="accion-field-label">Disponibilidad de cupo <span class="accion-required">*</span></label>
                  <div class="aceptar-disp-btns">
                    <button
                      type="button"
                      class="aceptar-disp-btn aceptar-disp-ok"
                      :class="{ active: !formAceptar.sin_disponibilidad }"
                      @click="formAceptar.sin_disponibilidad = false"
                    >
                      <component :is="CheckIcon" class="w-4 h-4" />
                      <span>Sí, hay cupo<small>Se envía el código ahora</small></span>
                    </button>
                    <button
                      type="button"
                      class="aceptar-disp-btn aceptar-disp-warn"
                      :class="{ active: formAceptar.sin_disponibilidad }"
                      @click="formAceptar.sin_disponibilidad = true"
                    >
                      <component :is="HourglassIcon" class="w-4 h-4" />
                      <span>Sin disponibilidad<small>Queda en espera de cupo</small></span>
                    </button>
                  </div>
                </div>
                <div class="mb-1.5">
                  <label class="accion-field-label">EPS <span class="accion-required">*</span></label>
                  <el-select v-model="formAceptar.eps" filterable placeholder="Seleccione la EPS con su contrato" class="w-full" popper-class="negar-motivo-popper">
                    <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
                  </el-select>
                </div>
                <div v-if="formAceptar.sin_disponibilidad" class="accion-sin-cama-callout mb-1.5">
                  <component :is="SendIcon" class="w-3.5 h-3.5 shrink-0" />
                  <span>A la clínica le llegará: <strong>«Paciente aceptado en espera de cupo — le estaremos enviando el código cuando haya disponibilidad»</strong>. Cuando haya cupo, usa «Asignar cupo y enviar código» desde el menú de la fila.</span>
                </div>
                <div v-if="formAceptar.sin_disponibilidad" class="mb-1.5">
                  <label class="accion-field-label">Lugar al que será referido <span class="accion-required">*</span></label>
                  <el-select v-model="formAceptar.lugar_llegada" placeholder="Seleccione el servicio" class="w-full" popper-class="negar-motivo-popper">
                    <el-option v-for="l in LUGARES_LLEGADA" :key="l.value" :label="l.label" :value="l.value" />
                  </el-select>
                  <p class="accion-field-hint">Queda guardado para el personal de referencia. No se envía a la clínica hasta que se asigne el código de aceptación.</p>
                </div>
                <template v-if="!formAceptar.sin_disponibilidad">
                <div class="accion-gomedisys-callout mb-1.5">
                  <div class="accion-gomedisys-callout-text">
                    <component :is="SendIcon" class="w-3.5 h-3.5 shrink-0" />
                    <span>¿Aún no está registrado en Gomedisys? Envíalo primero para obtener el código REF.</span>
                  </div>
                  <button
                    type="button"
                    class="accion-gomedisys-btn"
                    :class="{ 'accion-gomedisys-btn-enviando': enviandoGomedisysId === solicitudSeleccionada.id }"
                    @click="abrirGomedisys(solicitudSeleccionada)"
                  >
                    <component :is="enviandoGomedisysId === solicitudSeleccionada.id ? Loader2Icon : SendIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': enviandoGomedisysId === solicitudSeleccionada.id }" />
                    Enviar a Gomedisys
                  </button>
                </div>
                <div class="grid grid-cols-2 gap-2 mb-1.5">
                  <div>
                    <label class="accion-field-label">Sede <span class="accion-required">*</span></label>
                    <el-select v-model="formAceptar.sede" placeholder="Seleccione la sede" class="w-full" popper-class="negar-motivo-popper">
                      <el-option label="Urgencias" value="URGENCIAS" />
                      <el-option label="Central de Especialistas" value="CENTRAL DE ESPECIALISTAS" />
                      <el-option label="CAC Santa Bárbara" value="CAC SANTA BARBARA" />
                    </el-select>
                  </div>
                  <div>
                    <label class="accion-field-label">Código Gomedisys (REF) <span class="accion-required">*</span></label>
                    <el-input v-model="formAceptar.codigo_aceptacion" placeholder="Ej: REF00045821" />
                  </div>
                  <div>
                    <label class="accion-field-label">Hora de llegada <span class="accion-required">*</span></label>
                    <el-time-picker
                      v-model="formAceptar.hora_llegada"
                      placeholder="Ej: 2:30 PM"
                      format="hh:mm A"
                      value-format="HH:mm"
                      :clearable="false"
                      class="w-full"
                    />
                  </div>
                  <div>
                    <label class="accion-field-label">Lugar <span class="accion-required">*</span></label>
                    <el-select v-model="formAceptar.lugar_llegada" placeholder="Seleccione el servicio" class="w-full" popper-class="negar-motivo-popper">
                      <el-option v-for="l in LUGARES_LLEGADA" :key="l.value" :label="l.label" :value="l.value" />
                    </el-select>
                  </div>
                </div>
                <p class="accion-field-hint mb-1.5">El código real que generó Gomedisys al registrar al paciente allá — no se inventa acá.</p>
                </template>
                <div>
                  <label class="accion-field-label">Observaciones</label>
                  <el-input v-model="formAceptar.observaciones_respuesta" type="textarea" :rows="2" placeholder="Opcional" />
                </div>
                <div class="mb-1.5">
                  <label class="accion-field-label">Correo de notificación a la clínica</label>
                  <div class="correo-destino-row">
                    <span class="correo-destino-chip" :title="correoDestinoClinica">{{ correoDestinoClinica }}</span>
                    <el-select v-model="formAceptar.correos_extra" multiple filterable allow-create default-first-option :reserve-keyword="false" collapse-tags :max-collapse-tags="2" placeholder="Escribe correos y presiona Enter (opcional)" class="flex-1" />
                  </div>
                </div>
                <div class="negar-attach">
                  <input
                    ref="soportesAceptarInput"
                    type="file"
                    class="hidden"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tiff,.svg,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar"
                    @change="agregarSoportesAceptar"
                  />
                  <button type="button" class="sol-attach-btn" @click="soportesAceptarInput?.click()">
                    <component :is="PaperclipIcon" class="w-3.5 h-3.5" /> Adjuntar soporte
                  </button>
                  <span class="sol-attach-hint">Imágenes o PDF · hasta 5 de 10 MB</span>
                  <div v-if="soportesAceptar.length" class="sol-attach-list">
                    <span v-for="(archivo, i) in soportesAceptar" :key="`${archivo.name}-${i}`" class="sol-attach-chip">
                      <component :is="PaperclipIcon" class="w-3 h-3" />
                      <span class="sol-attach-name" :title="archivo.name">{{ archivo.name }}</span>
                      <button type="button" class="sol-attach-del" aria-label="Quitar archivo" @click="soportesAceptar.splice(i, 1)">
                        <component :is="XIcon" class="w-3 h-3" />
                      </button>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="detalle-footer">
            <button type="button" class="accion-cancelar-btn" @click="modalAceptar = false">Cancelar</button>
            <button type="button" class="accion-confirmar-btn accion-confirmar-success" :disabled="procesando" @click="aceptar">
              <component :is="procesando ? Loader2Icon : CheckIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': procesando }" />
              Confirmar aceptación
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- ── Modal: Negar ── -->
    <el-dialog v-model="modalNegar" width="540px" class="accion-dialog accion-dialog-negar" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="modalNegar = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon accion-icon-danger">
              <component :is="XIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Negar solicitud</p>
              <p class="detalle-head-sub">ID #{{ solicitudSeleccionada.id }} · indica el motivo de la negación</p>
            </div>
          </div>

          <div class="detalle-body">
            <div class="accion-patient-card">
              <div class="accion-patient-avatar">{{ inicialesPaciente(solicitudSeleccionada) }}</div>
              <div class="accion-patient-info">
                <strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong>
                <span>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }} · {{ solicitudSeleccionada.edad }} años · {{ solicitudSeleccionada.especialidad_requerida }}</span>
              </div>
            </div>

            <div class="detalle-card">
              <div class="card-body accion-form-body">
                <div class="grid grid-cols-2 gap-2 mb-2">
                  <div>
                    <label class="accion-field-label">Fecha</label>
                    <el-input :model-value="formNegar.fecha_respuesta" disabled />
                  </div>
                  <div>
                    <label class="accion-field-label">Hora</label>
                    <el-input :model-value="formNegar.hora_respuesta" disabled />
                  </div>
                </div>
                <div class="mb-2">
                  <label class="accion-field-label">Quien responde</label>
                  <el-input :model-value="formNegar.nombre_quien_responde" disabled />
                </div>
                <div class="mb-2">
                  <label class="accion-field-label">EPS <span class="accion-required">*</span></label>
                  <el-select v-model="formNegar.eps" filterable placeholder="Seleccione la EPS con su contrato" class="w-full" popper-class="negar-motivo-popper">
                    <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
                  </el-select>
                </div>
                <div class="mb-2">
                  <label class="accion-field-label">Grupo de negación <span class="accion-required">*</span></label>
                  <div class="negar-grupo-btns">
                    <button
                      type="button"
                      class="negar-grupo-btn"
                      :class="{ active: formNegar.grupo_negacion === 'no_red' }"
                      @click="seleccionarGrupoNegacion('no_red')"
                    >
                      <component :is="UserXIcon" class="w-4 h-4" />
                      <span>Paciente no RED<small>Motivo libre</small></span>
                    </button>
                    <button
                      type="button"
                      class="negar-grupo-btn"
                      :class="{ active: formNegar.grupo_negacion === 'poblacion_objeto' }"
                      @click="seleccionarGrupoNegacion('poblacion_objeto')"
                    >
                      <component :is="UsersIcon" class="w-4 h-4" />
                      <span>Población objeto<small>Lista de motivos</small></span>
                    </button>
                  </div>
                </div>
                <div v-if="formNegar.grupo_negacion === 'poblacion_objeto'" class="mb-2">
                  <label class="accion-field-label">Motivo de negación <span class="accion-required">*</span></label>
                  <el-select v-model="formNegar.motivo_negacion" placeholder="Seleccione el motivo" class="w-full" popper-class="negar-motivo-popper">
                    <el-option v-for="m in MOTIVOS_NEGACION_POBLACION" :key="m" :label="m" :value="m" />
                  </el-select>
                </div>
                <div v-else-if="formNegar.grupo_negacion === 'no_red'" class="mb-2">
                  <label class="accion-field-label">Motivo de negación <span class="accion-required">*</span></label>
                  <el-input v-model="formNegar.motivo_negacion" type="textarea" :rows="3" placeholder="Indique el motivo..." />
                </div>
                <p v-else class="negar-grupo-hint">Selecciona el grupo para habilitar el motivo de negación.</p>
                <div class="negar-attach">
                  <input
                    ref="soportesNegarInput"
                    type="file"
                    class="hidden"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tiff,.svg,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar"
                    @change="agregarSoportesNegar"
                  />
                  <button type="button" class="sol-attach-btn" @click="soportesNegarInput?.click()">
                    <component :is="PaperclipIcon" class="w-3.5 h-3.5" /> Adjuntar soporte
                  </button>
                  <span class="sol-attach-hint">Imágenes o PDF de evidencia · hasta 5 de 10 MB</span>
                  <div v-if="soportesNegar.length" class="sol-attach-list">
                    <span v-for="(archivo, i) in soportesNegar" :key="`${archivo.name}-${i}`" class="sol-attach-chip">
                      <component :is="PaperclipIcon" class="w-3 h-3" />
                      <span class="sol-attach-name" :title="archivo.name">{{ archivo.name }}</span>
                      <button type="button" class="sol-attach-del" aria-label="Quitar archivo" @click="soportesNegar.splice(i, 1)">
                        <component :is="XIcon" class="w-3 h-3" />
                      </button>
                    </span>
                  </div>
                </div>
                <div>
                  <label class="accion-field-label">Observaciones adicionales</label>
                  <el-input v-model="formNegar.observaciones_respuesta" type="textarea" :rows="2" placeholder="Opcional" />
                </div>
                <div>
                  <label class="accion-field-label">Correo de notificación a la clínica</label>
                  <div class="correo-destino-row">
                    <span class="correo-destino-chip" :title="correoDestinoClinica">{{ correoDestinoClinica }}</span>
                    <el-select v-model="formNegar.correos_extra" multiple filterable allow-create default-first-option :reserve-keyword="false" collapse-tags :max-collapse-tags="2" placeholder="Escribe correos y presiona Enter (opcional)" class="flex-1" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="detalle-footer">
            <button type="button" class="accion-cancelar-btn" @click="modalNegar = false">Cancelar</button>
            <button type="button" class="accion-confirmar-btn accion-confirmar-danger" :disabled="procesando" @click="negar">
              <component :is="procesando ? Loader2Icon : XIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': procesando }" />
              Confirmar negación
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- ── Modal: Cambiar estado ── -->
    <el-dialog v-model="modalCambiar" width="560px" class="accion-dialog" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="modalCambiar = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon accion-icon-violet">
              <component :is="RotateCcwIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Cambiar estado</p>
              <p class="detalle-head-sub">ID #{{ solicitudSeleccionada.id }} · la clínica será notificada del cambio</p>
            </div>
          </div>

          <div class="detalle-body">
            <div class="accion-patient-card">
              <div class="accion-patient-avatar">{{ inicialesPaciente(solicitudSeleccionada) }}</div>
              <div class="accion-patient-info">
                <strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong>
                <span>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }} · {{ solicitudSeleccionada.edad }} años · {{ solicitudSeleccionada.especialidad_requerida }}</span>
              </div>
            </div>

            <div class="detalle-card">
              <div class="card-body accion-form-body">
                <div class="grid grid-cols-3 gap-2 mb-1.5">
                  <div>
                    <label class="accion-field-label">Fecha</label>
                    <el-input :model-value="formCambiar.fecha_respuesta" disabled />
                  </div>
                  <div>
                    <label class="accion-field-label">Hora</label>
                    <el-input :model-value="formCambiar.hora_respuesta" disabled />
                  </div>
                  <div>
                    <label class="accion-field-label">Quien responde</label>
                    <el-input :model-value="formCambiar.nombre_quien_responde" disabled />
                  </div>
                </div>
                <div class="mb-1.5">
                  <label class="accion-field-label">Nuevo estado <span class="accion-required">*</span></label>
                  <el-select v-model="formCambiar.estado" placeholder="Seleccione el estado" class="w-full" popper-class="negar-motivo-popper">
                    <el-option v-for="op in opcionesCambioEstado" :key="op.value" :label="op.label" :value="op.value" />
                  </el-select>
                </div>
                <div v-if="['en_espera', 'pendiente_cama', 'negado'].includes(formCambiar.estado)" class="mb-1.5">
                  <label class="accion-field-label">EPS <span class="accion-required">*</span></label>
                  <el-select v-model="formCambiar.eps" filterable placeholder="Seleccione la EPS con su contrato" class="w-full" popper-class="negar-motivo-popper">
                    <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
                  </el-select>
                </div>
                <div v-if="formCambiar.estado === 'en_espera'" class="grid grid-cols-2 gap-2 mb-1.5">
                  <div>
                    <label class="accion-field-label">Sede <span class="accion-required">*</span></label>
                    <el-select v-model="formCambiar.sede" placeholder="Seleccione la sede" class="w-full" popper-class="negar-motivo-popper">
                      <el-option label="Urgencias" value="URGENCIAS" />
                      <el-option label="Central de Especialistas" value="CENTRAL DE ESPECIALISTAS" />
                      <el-option label="CAC Santa Bárbara" value="CAC SANTA BARBARA" />
                    </el-select>
                  </div>
                  <div>
                    <label class="accion-field-label">Código Gomedisys (REF) <span class="accion-required">*</span></label>
                    <el-input v-model="formCambiar.codigo_aceptacion" placeholder="Ej: REF00045821" />
                  </div>
                  <div>
                    <label class="accion-field-label">Hora de llegada <span class="accion-required">*</span></label>
                    <el-time-picker
                      v-model="formCambiar.hora_llegada"
                      placeholder="Ej: 2:30 PM"
                      format="hh:mm A"
                      value-format="HH:mm"
                      :clearable="false"
                      class="w-full"
                    />
                  </div>
                  <div>
                    <label class="accion-field-label">Lugar <span class="accion-required">*</span></label>
                    <el-select v-model="formCambiar.lugar_llegada" placeholder="Seleccione el servicio" class="w-full" popper-class="negar-motivo-popper">
                      <el-option v-for="l in LUGARES_LLEGADA" :key="l.value" :label="l.label" :value="l.value" />
                    </el-select>
                  </div>
                </div>
                <div v-if="formCambiar.estado === 'completado'" class="mb-1.5">
                  <label class="accion-field-label">Número de ingreso (Gomedisys) <span class="accion-required">*</span></label>
                  <el-input v-model="formCambiar.numero_ingreso" placeholder="Ej: 1045821" />
                </div>
                <template v-if="formCambiar.estado === 'negado'">
                  <div class="mb-1.5">
                    <label class="accion-field-label">Grupo de negación <span class="accion-required">*</span></label>
                    <div class="negar-grupo-btns">
                      <button
                        type="button"
                        class="negar-grupo-btn"
                        :class="{ active: formCambiar.grupo_negacion === 'no_red' }"
                        @click="formCambiar.grupo_negacion = 'no_red'; formCambiar.motivo_negacion = ''"
                      >
                        <component :is="UserXIcon" class="w-4 h-4" />
                        <span>Paciente no RED<small>Motivo libre</small></span>
                      </button>
                      <button
                        type="button"
                        class="negar-grupo-btn"
                        :class="{ active: formCambiar.grupo_negacion === 'poblacion_objeto' }"
                        @click="formCambiar.grupo_negacion = 'poblacion_objeto'; formCambiar.motivo_negacion = ''"
                      >
                        <component :is="UsersIcon" class="w-4 h-4" />
                        <span>Población objeto<small>Lista de motivos</small></span>
                      </button>
                    </div>
                  </div>
                  <div v-if="formCambiar.grupo_negacion === 'poblacion_objeto'" class="mb-1.5">
                    <label class="accion-field-label">Motivo de negación <span class="accion-required">*</span></label>
                    <el-select v-model="formCambiar.motivo_negacion" placeholder="Seleccione el motivo" class="w-full" popper-class="negar-motivo-popper">
                      <el-option v-for="m in MOTIVOS_NEGACION_POBLACION" :key="m" :label="m" :value="m" />
                    </el-select>
                  </div>
                  <div v-else-if="formCambiar.grupo_negacion === 'no_red'" class="mb-1.5">
                    <label class="accion-field-label">Motivo de negación <span class="accion-required">*</span></label>
                    <el-input v-model="formCambiar.motivo_negacion" type="textarea" :rows="3" placeholder="Indique el motivo..." />
                  </div>
                  <p v-else class="negar-grupo-hint">Selecciona el grupo para habilitar el motivo de negación.</p>
                </template>
                <div>
                  <label class="accion-field-label">Motivo del cambio <span class="accion-required">*</span></label>
                  <el-input v-model="formCambiar.motivo" type="textarea" :rows="2" placeholder="Explique por qué cambia el estado — va en el correo a la clínica..." />
                </div>
                <div>
                  <label class="accion-field-label">Correo de notificación a la clínica</label>
                  <div class="correo-destino-row">
                    <span class="correo-destino-chip" :title="correoDestinoClinica">{{ correoDestinoClinica }}</span>
                    <el-select v-model="formCambiar.correos_extra" multiple filterable allow-create default-first-option :reserve-keyword="false" collapse-tags :max-collapse-tags="2" placeholder="Escribe correos y presiona Enter (opcional)" class="flex-1" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="detalle-footer">
            <button type="button" class="accion-cancelar-btn" @click="modalCambiar = false">Cancelar</button>
            <button type="button" class="accion-confirmar-btn accion-confirmar-violet" :disabled="procesando" @click="cambiarEstado">
              <component :is="procesando ? Loader2Icon : RotateCcwIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': procesando }" />
              Cambiar y notificar
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- ── Modal: Nota de seguimiento ── -->
    <el-dialog v-model="modalNota" width="520px" class="accion-dialog" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="modalNota = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon accion-icon-amber">
              <component :is="StickyNoteIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Nota de seguimiento</p>
              <p class="detalle-head-sub">ID #{{ solicitudSeleccionada.id }} · nota interna, no se notifica a la clínica</p>
            </div>
          </div>

          <div class="detalle-body">
            <div class="accion-patient-card">
              <div class="accion-patient-avatar">{{ inicialesPaciente(solicitudSeleccionada) }}</div>
              <div class="accion-patient-info">
                <strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong>
                <span>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }} · {{ solicitudSeleccionada.especialidad_requerida }}</span>
              </div>
            </div>

            <div class="detalle-card">
              <div class="card-body accion-form-body">
                <div class="grid grid-cols-2 gap-2 mb-1.5">
                  <div>
                    <label class="accion-field-label">Fecha y hora</label>
                    <el-input :model-value="`${fechaActual()} ${horaActual()}`" disabled />
                  </div>
                  <div>
                    <label class="accion-field-label">Registra</label>
                    <el-input :model-value="authStore.user?.full_name ?? ''" disabled />
                  </div>
                </div>
                <div>
                  <label class="accion-field-label">Nota <span class="accion-required">*</span></label>
                  <el-input v-model="notaTexto" type="textarea" :rows="3" maxlength="1000" show-word-limit placeholder="Ej: Se llamó a la clínica — confirman traslado mañana temprano..." />
                </div>
                <div v-if="notasSeguimiento.length" class="notas-list notas-list-modal">
                  <p class="notas-list-title">Notas anteriores ({{ notasSeguimiento.length }})</p>
                  <div class="notas-list-scroll">
                    <div v-for="nota in notasSeguimiento" :key="nota.id" class="nota-item">
                      <div class="nota-item-head">
                        <component :is="StickyNoteIcon" class="w-3 h-3 nota-item-icon" />
                        <span class="nota-item-autor">{{ nota.titulo.replace('Nota de seguimiento — ', '') }}</span>
                        <span class="nota-item-fecha">{{ formatFecha(nota.created_at) }} · {{ horaDeFecha(nota.created_at) }}</span>
                      </div>
                      <p class="nota-item-texto">{{ nota.descripcion }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="detalle-footer">
            <button type="button" class="accion-cancelar-btn" @click="modalNota = false">Cancelar</button>
            <button type="button" class="accion-confirmar-btn accion-confirmar-amber" :disabled="guardandoNota" @click="guardarNota">
              <component :is="guardandoNota ? Loader2Icon : StickyNoteIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': guardandoNota }" />
              Guardar nota
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- ── Modal: Ficha de datos para Gomedisys ── -->
    <el-dialog v-model="modalFichaGomedisys" width="560px" class="accion-dialog" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="modalFichaGomedisys = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon accion-icon-violet">
              <component :is="ClipboardListIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Datos para Gomedisys</p>
              <p class="detalle-head-sub">ID #{{ solicitudSeleccionada.id }} · copia cada campo y pégalo en el registro de referencia</p>
            </div>
          </div>

          <div class="detalle-body">
            <div class="accion-patient-card">
              <div class="accion-patient-avatar">{{ inicialesPaciente(solicitudSeleccionada) }}</div>
              <div class="accion-patient-info">
                <strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong>
                <span>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }} · {{ solicitudSeleccionada.edad }} años · {{ solicitudSeleccionada.especialidad_requerida }}</span>
              </div>
            </div>

            <div class="ficha-list">
              <div v-for="campo in fichaGomedisys" :key="campo.label" class="ficha-item">
                <div class="ficha-item-text">
                  <span class="ficha-item-label">{{ campo.label }}</span>
                  <strong class="ficha-item-value" :title="campo.valor">{{ campo.valor || '—' }}</strong>
                </div>
                <button
                  type="button"
                  class="ficha-copy-btn"
                  :class="{ 'ficha-copy-btn-ok': campoCopiado === campo.label }"
                  :disabled="!campo.valor"
                  @click="copiarDato(campo)"
                >
                  <component :is="campoCopiado === campo.label ? CheckIcon : CopyIcon" class="w-3 h-3" />
                  {{ campoCopiado === campo.label ? 'Copiado' : 'Copiar' }}
                </button>
              </div>
            </div>
          </div>

          <div class="detalle-footer">
            <button type="button" class="accion-cancelar-btn" @click="modalFichaGomedisys = false">Cerrar</button>
            <button type="button" class="accion-confirmar-btn accion-confirmar-violet" @click="copiarTodoGomedisys">
              <component :is="CopyIcon" class="w-3.5 h-3.5" />
              Copiar todo
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- ── Modal: Confirmar ingreso (Gomedisys o manual) ── -->
    <el-dialog v-model="modalConfirmarIngreso" width="560px" class="accion-dialog" :show-close="false" align-center>
      <template v-if="solicitudSeleccionada">
        <div class="detalle-content">
          <button type="button" class="detalle-close-btn" @click="modalConfirmarIngreso = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon accion-icon-teal">
              <component :is="SearchIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Confirmar ingreso</p>
              <p class="detalle-head-sub">ID #{{ solicitudSeleccionada.id }} · completa con el número de ingreso o marca por qué no llegó</p>
            </div>
          </div>

          <div class="detalle-body">
            <div class="grid gap-2" :class="datosIngresoGomedisys ? 'grid-cols-2' : 'grid-cols-1'">
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-blue"><component :is="UserIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-blue">Datos de la solicitud</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Nombre</span><strong>{{ nombreCompleto(solicitudSeleccionada) }}</strong></div>
                  <div class="data-row"><span>Documento</span><strong>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }}</strong></div>
                  <div class="data-row"><span>Edad</span><strong>{{ solicitudSeleccionada.edad }} años</strong></div>
                  <div class="data-row"><span>Género</span><strong>{{ solicitudSeleccionada.genero === 'M' ? 'Masc.' : 'Fem.' }}</strong></div>
                </div>
              </div>
              <div v-if="datosIngresoGomedisys" class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-violet"><component :is="SearchIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-violet">Encontrado en Gomedisys</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Nombre</span><strong>{{ datosIngresoGomedisys.fullname }}</strong></div>
                  <div class="data-row"><span>Documento</span><strong>{{ datosIngresoGomedisys.document_type }} {{ datosIngresoGomedisys.identification_number }}</strong></div>
                  <div class="data-row"><span>Nacimiento</span><strong>{{ datosIngresoGomedisys.birthdate || '—' }}</strong></div>
                  <div class="data-row"><span>Sexo</span><strong>{{ datosIngresoGomedisys.sex || '—' }}</strong></div>
                  <div class="data-row"><span>Fecha de ingreso</span><strong>{{ datosIngresoGomedisys.admission_date || '—' }}</strong></div>
                </div>
              </div>
            </div>
            <div v-if="!datosIngresoGomedisys" class="accion-sin-cama-callout mt-2">
              <component :is="SearchIcon" class="w-3.5 h-3.5 shrink-0" />
              <span>Sin ingreso consultado en Gomedisys — regístrelo manual o indique por qué el paciente no llegó.</span>
            </div>

            <!-- No llegó: siempre visible, sin paso extra -->
            <div class="detalle-card mt-2">
              <div class="card-body accion-form-body">
                <label class="accion-field-label">Si el paciente no llegó — motivo de no ingreso</label>
                <div class="flex gap-2">
                  <el-select v-model="motivoNoIngreso" placeholder="Seleccione el motivo" class="flex-1" popper-class="negar-motivo-popper">
                    <el-option v-for="m in MOTIVOS_NO_INGRESO" :key="m" :label="m" :value="m" />
                  </el-select>
                  <button type="button" class="accion-confirmar-btn accion-confirmar-danger" :disabled="procesandoIngreso" @click="registrarNoIngreso">
                    <component :is="procesandoIngreso ? Loader2Icon : UserXIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': procesandoIngreso }" />
                    No llegó
                  </button>
                </div>
                <p class="accion-field-hint mt-1.5">Cierra la referencia como negada y avisa a la clínica con este motivo.</p>
              </div>
            </div>

            <div class="detalle-card mt-2">
              <div class="card-body accion-form-body">
                <label class="accion-field-label">Correo de notificación a la clínica</label>
                <div class="correo-destino-row">
                  <span class="correo-destino-chip" :title="correoDestinoClinica">{{ correoDestinoClinica }}</span>
                  <el-select v-model="correosExtraIngreso" multiple filterable allow-create default-first-option :reserve-keyword="false" collapse-tags :max-collapse-tags="2" placeholder="Escribe correos y presiona Enter (opcional)" class="flex-1" />
                </div>
              </div>
            </div>
          </div>

          <div class="detalle-footer">
            <button type="button" class="accion-cancelar-btn" @click="modalConfirmarIngreso = false">Cancelar</button>
            <div class="ingreso-footer-group">
              <el-input v-model="numeroIngresoManual" placeholder="N.° de ingreso" class="ingreso-num-input" />
              <button type="button" class="accion-confirmar-btn accion-confirmar-violet" :disabled="procesandoIngreso" @click="confirmarIngresoPaciente">
                <component :is="procesandoIngreso ? Loader2Icon : CheckIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': procesandoIngreso }" />
                Completar
              </button>
            </div>
          </div>
        </div>
      </template>
    </el-dialog>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, type Ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePolling } from '@/lib/usePolling';
import { actualizarSiCambio } from '@/lib/silentRefresh';
import { useStorage, useDebounceFn, onClickOutside } from '@vueuse/core';
import notify from '@/plugins/toast';
import { exportarSolicitudPdf } from '@/lib/exportarSolicitudPdf';
import {
  Search as SearchIcon,
  RefreshCw as RefreshIcon,
  Eye as EyeIcon,
  Check as CheckIcon,
  X as XIcon,
  FileText as FileTextIcon,
  Clock as ClockIcon,
  CheckCircle as CheckCircleIcon,
  XCircle as XCircleIcon,
  ClipboardList as ClipboardListIcon,
  Paperclip as PaperclipIcon,
  Download as DownloadIcon,
  Image as ImageIcon,
  ZoomIn as ZoomInIcon,
  ZoomOut as ZoomOutIcon,
  Maximize as MaximizeIcon,
  Expand as ExpandIcon,
  Minimize as MinimizeIcon,
  ChevronLeft as ChevronLeftIcon,
  ChevronRight as ChevronRightIcon,
  Send as SendIcon,
  Copy as CopyIcon,
  Shield as ShieldIcon,
  User as UserIcon,
  Building2 as BuildingIcon,
  Hourglass as HourglassIcon,
  Loader2 as Loader2Icon,
  FileDown as FileDownIcon,
  RotateCcw as RotateCcwIcon,
  MoreVertical as MoreIcon,
  SlidersHorizontal as SlidersIcon,
  Gauge as GaugeIcon,
  Activity as ActivityIcon,
  UserX as UserXIcon,
  Users as UsersIcon,
  Mail as MailIcon,
  MessageSquare as MessageIcon,
  MonitorSmartphone as MonitorSmartphoneIcon,
  StickyNote as StickyNoteIcon,
} from '@lucide/vue';
import http from '@/plugins/axios';
import { exportarSolicitudesExcel } from '@/lib/exportarSolicitudesExcel';
import StatCard from '@/components/ui/StatCard.vue';
import DateRangeFilter from '@/components/ui/DateRangeFilter.vue';
import PanelHelp from '@/components/ui/PanelHelp.vue';
import { autocompletarEnGomedisys } from '@/lib/gomedisys';
import { EPS_LIST, LUGARES_LLEGADA } from '@/data/referencia';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

// Los paneles de métricas de la cola (salud de la cola, especialidades) solo
// los ven los roles con el permiso solicitudes.analytics — p. ej. la jefe.
const puedeVerAnalitica = computed(() => authStore.hasPermission('solicitudes.analytics'));
const route = useRoute();
const router = useRouter();

interface Clinica {
  id: number;
  nombre: string;
  email?: string;
}

interface Solicitud {
  id: number;
  clinica_id: number | null;
  clinica?: Clinica;
  institucion_remitente?: string;
  fecha: string;
  hora: string;
  primer_nombre: string;
  segundo_nombre?: string;
  primer_apellido: string;
  segundo_apellido?: string;
  genero: 'M' | 'F';
  edad: number;
  tipo_documento: string;
  numero_documento: string;
  eps: string;
  diagnostico: string;
  diagnosticos?: { id: number; codigo_cie10: string; descripcion: string }[];
  municipio_capita: string;
  direccion_paciente?: string;
  telefono_paciente?: string;
  especialidad_requerida: string;
  tipo_referencia?: string;
  urgencia_vital?: boolean;
  servicio_ubicacion_actual: string;
  servicio_remision?: string;
  quien_remitente?: string;
  telefono_contacto?: string;
  correo_contacto?: string;
  resumen_historia_clinica: string;
  via_contacto?: string;
  gestante?: boolean;
  condicion_especial?: string;
  observaciones?: string;
  estado: 'pendiente' | 'pendiente_cama' | 'en_espera' | 'completado' | 'negado' | 'cancelado';
  codigo_aceptacion?: string;
  hora_respuesta?: string;
  hora_llegada?: string;
  lugar_llegada?: string;
  inbox_mensaje_id?: number | null;
  inbox_mensaje_id?: number | null;
  sede?: string;
  motivo_negacion?: string;
  grupo_negacion?: string;
  numero_ingreso?: number;
  nombre_quien_responde?: string;
  observaciones_respuesta?: string;
  adjuntos?: { id: number; nombre_original: string; mime_type: string; tamano: number }[];
  eventos?: { id: number; tipo: string; titulo: string; descripcion?: string; created_at: string }[];
  evoluciones?: {
    id: number;
    nota: string;
    created_at: string;
    adjuntos?: { id: number; nombre_original: string; mime_type: string; tamano: number }[];
    respuestas?: RespuestaEvolucion[];
  }[];
  created_at: string;
}

interface RespuestaEvolucion {
  id: number;
  mensaje: string;
  respondido_por: string | null;
  correo_enviado: boolean;
  created_at: string;
}

const solicitudes = ref<Solicitud[]>([]);
const cargando = ref(false);
const procesando = ref(false);
// Los filtros se persisten por usuario — con una llave compartida, lo que
// eligiera una sesión se le aplicaba también a otra cuenta en el mismo PC.
const uidFiltros = authStore.user?.id ?? 0;
const filtro = useStorage(`sol-filtro-u${uidFiltros}`, { buscar: '' });
const buscarDebounced = ref(filtro.value.buscar);
const updateBuscarDebounced = useDebounceFn((val: string) => { buscarDebounced.value = val; }, 300);
watch(() => filtro.value.buscar, (val) => updateBuscarDebounced(val));
const tabActiva = useStorage<'todas' | 'pendiente' | 'pendiente_cama' | 'en_espera'>(`sol-tab-u${uidFiltros}`, 'todas');
// 'completado'/'negado' ya no viven aquí — si quedó guardado un tab así de
// antes de mover esos estados al histórico, se reinicia para no mostrar una
// tabla vacía sin explicación.
if (!['todas', 'pendiente', 'pendiente_cama', 'en_espera'].includes(tabActiva.value)) {
  tabActiva.value = 'todas';
}

const sortKey = ref<'paciente' | 'clinica' | 'especialidad' | 'eps' | 'fecha'>('fecha');
// Más reciente arriba por defecto, igual que en Histórico (a propósito,
// pidió explícitamente invertir el orden que se dejó ascendente antes).
const sortDir = ref<'asc' | 'desc'>('desc');
const resaltarId = ref<number | null>(null);
const paginaActual = ref(1);
const itemsPorPagina = ref(15);
watch(itemsPorPagina, () => { paginaActual.value = 1; });

// Filtros del botón "Filtros" (todos se aplican en cliente sobre la lista ya cargada).
const filtrosAbiertos = ref(false);
const filtrosWrap = ref<HTMLElement | null>(null);
// Los menús de el-select/el-date-picker van teleportados a <body>: un clic en
// una opción también es "fuera" del wrap — sin esta comprobación el panel se
// cierra en el mismo gesto y el filtro nunca llega a aplicarse.
onClickOutside(filtrosWrap, (e) => {
  const enPopper = e.composedPath().some(
    el => el instanceof HTMLElement && el.matches('.el-popper, .el-popper *'),
  );
  if (!enPopper) filtrosAbiertos.value = false;
});
const filtroClinica = ref<number | string | ''>('');
const filtroEspecialidad = ref('');
const filtroEps = ref('');
const filtroOrigen = ref<'' | 'correo' | 'aplicativo'>('');
const rangoFechas = ref<[string, string] | null>(null);

// Datos del panel lateral (dona, barras por día, top clínicas) y de los chips
// de completadas/negadas — vienen del endpoint de stats del dashboard.
interface DashStats {
  solicitudes: { total: number; pendientes: number; en_espera: number; completadas: number; negadas: number };
  filtros: { especialidades: string[]; eps: string[]; clinicas: { id: number; nombre: string }[] };
  tendencia: { label: string; total: number }[];
  tendencia_anterior: { fecha: string; total: number }[];
  tendencia_pendientes: { fecha: string; total: number }[];
  tendencia_pendientes_anterior: { fecha: string; total: number }[];
  tendencia_en_espera: { fecha: string; total: number }[];
  tendencia_en_espera_anterior: { fecha: string; total: number }[];
  tendencia_completadas: { fecha: string; total: number }[];
  tendencia_completadas_anterior: { fecha: string; total: number }[];
  top_clinicas: { nombre: string; total: number; pct: number; serie: number[] }[];
}
const dash = ref<DashStats | null>(null);

const modalDetalle = ref(false);
const modalEvoluciones = ref(false);

const modalAceptar = ref(false);
const modalSolicitarEvolucion = ref(false);
const enviandoEvolucion = ref(false);
const formEvolucion = ref<{ mensaje: string; correos_extra: string[] }>({ mensaje: '', correos_extra: [] });
const modalNegar = ref(false);
const soportesNegar = ref<File[]>([]);
const soportesNegarInput = ref<HTMLInputElement | null>(null);
const soportesAceptar = ref<File[]>([]);
const soportesAceptarInput = ref<HTMLInputElement | null>(null);
const modalPdf = ref(false);
const solicitudSeleccionada = ref<Solicitud | null>(null);
const enviandoGomedisysId = ref<number | null>(null);
const consultandoIngresoId = ref<number | null>(null);
const modalFichaGomedisys = ref(false);
const campoCopiado = ref<string | null>(null);
const adjuntoActivo = ref<{ id: number; nombre_original: string; mime_type: string; tamano: number } | null>(null);

const formAceptar = ref<{ [k: string]: string | boolean | string[] }>({ fecha_respuesta: '', hora_respuesta: '', nombre_quien_responde: '', sin_disponibilidad: false, sede: '', eps: '', codigo_aceptacion: '', hora_llegada: '', lugar_llegada: '', observaciones_respuesta: '', correos_extra: [] });
const formNegar = ref<{ [k: string]: string | string[] }>({ fecha_respuesta: '', hora_respuesta: '', grupo_negacion: '', motivo_negacion: '', eps: '', nombre_quien_responde: '', observaciones_respuesta: '', correos_extra: [] });
const modalCambiar = ref(false);
const formCambiar = ref<{ [k: string]: string | string[] }>({ fecha_respuesta: '', hora_respuesta: '', nombre_quien_responde: '', estado: '', motivo: '', sede: '', codigo_aceptacion: '', hora_llegada: '', lugar_llegada: '', numero_ingreso: '', eps: '', correos_extra: [], grupo_negacion: '', motivo_negacion: '' });

// Destinos posibles del cambio manual — "Nueva solicitud" queda excluida:
// las nuevas solo avanzan por el flujo normal (aceptar/negar). Tampoco se
// ofrece el estado actual.
const opcionesCambioEstado = computed(() => {
  const actual = solicitudSeleccionada.value?.estado;
  return [
    { label: 'Pendiente — en espera de cupo', value: 'pendiente_cama' },
    { label: 'Aceptado (con código)', value: 'en_espera' },
    { label: 'Completada', value: 'completado' },
    { label: 'Negada', value: 'negado' },
    { label: 'Cancelada', value: 'cancelado' },
  ].filter(op => op.value !== actual);
});

// Motivos cerrados cuando la negación es por "Población objeto" — en
// "Paciente no RED" el motivo queda libre (texto) como antes.
const MOTIVOS_NEGACION_POBLACION = [
  'No disponibilidad de especialidad',
  'No cupo en UCI',
  'No cupo en UCIN',
  'Direccionamiento administrativo',
  'No disponibilidad de cama en piso',
  'No disponibilidad de insumo/equipo',
  'Exclusión del PGP',
  'No transladable',
  'Sin respuesta por parte de intensivista',
  'Paciente no comentado por la EPS',
  'Soportes incompletos',
];

function seleccionarGrupoNegacion(grupo: 'no_red' | 'poblacion_objeto') {
  formNegar.value.grupo_negacion = grupo;
  formNegar.value.motivo_negacion = '';
}

function grupoNegacionLabel(grupo?: string): string {
  if (grupo === 'no_red') return 'Paciente no RED';
  if (grupo === 'poblacion_objeto') return 'Población objeto';
  return '';
}



interface PacienteGomedisys {
  fullname: string;
  identification_number: string;
  document_type: string;
  sex: string;
  birthdate: string;
  admission_number: number;
  admission_date: string | null;
}
const modalConfirmarIngreso = ref(false);
const datosIngresoGomedisys = ref<PacienteGomedisys | null>(null);
const procesandoIngreso = ref(false);

const resumen = computed(() => ({
  total: solicitudes.value.length,
  pendientes: solicitudes.value.filter(s => s.estado === 'pendiente').length,
  pendientesCama: solicitudes.value.filter(s => s.estado === 'pendiente_cama').length,
  enEspera: solicitudes.value.filter(s => s.estado === 'en_espera').length,
}));

const solicitudesEnEspera = computed(() => solicitudes.value.filter(s => s.estado === 'en_espera'));

/* ── Datos del panel lateral y stat cards (endpoint de stats del dashboard) ── */
const statsSol = computed(() => dash.value?.solicitudes ?? { total: 0, pendientes: 0, en_espera: 0, completadas: 0, negadas: 0 });
/* Las opciones de los dropdowns salen de la cola cargada — así toda opción
   siempre produce resultados (antes venían del DISTINCT global e incluían
   clínicas/especialidades cuyas solicitudes ya están todas en el histórico). */
const clinicasFiltro = computed(() => {
  const mapa = new Map<number | string, string>();
  for (const s of solicitudes.value) {
    if (s.clinica) mapa.set(s.clinica.id, s.clinica.nombre);
    else if (s.institucion_remitente) mapa.set(s.institucion_remitente, s.institucion_remitente);
  }
  return [...mapa.entries()].map(([id, nombre]) => ({ id, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre));
});
const especialidadesFiltro = computed(() =>
  [...new Set(solicitudes.value.map(s => s.especialidad_requerida).filter(Boolean))].sort((a, b) => a.localeCompare(b))
);

const serieTotal = computed(() => (dash.value?.tendencia ?? []).map(d => d.total));
const seriePendientes = computed(() => (dash.value?.tendencia_pendientes ?? []).map(d => d.total));
const serieEnEspera = computed(() => (dash.value?.tendencia_en_espera ?? []).map(d => d.total));
const serieCompletadas = computed(() => (dash.value?.tendencia_completadas ?? []).map(d => d.total));

/** Densifica la serie real (interpola entre días y suma una ondulación
 * determinística) para que el sparkline tenga picos aunque los datos
 * sean planos. Solo decorativa — los deltas usan las series reales. */
function ondular(serie: number[], seed: number): number[] {
  if (!serie.length) return serie;
  const n = 24;
  const max = Math.max(...serie, 1);
  return Array.from({ length: n }, (_, i) => {
    const pos = (i / (n - 1)) * (serie.length - 1);
    const j = Math.min(Math.floor(pos), serie.length - 1);
    const f = pos - j;
    const base = (serie[j] ?? 0) + (((serie[j + 1] ?? serie[j]) ?? 0) - (serie[j] ?? 0)) * f;
    const onda = max * 0.16 * (0.5 + 0.5 * Math.sin(i * 1.7 + seed))
      + max * 0.07 * Math.sin(i * 3.9 + seed * 1.3);
    return Math.max(0, base * 0.7 + onda);
  });
}

function deltaTexto(actual: number[], anterior: number[]): string {
  const a = actual.reduce((x, y) => x + y, 0);
  const b = anterior.reduce((x, y) => x + y, 0);
  if (!b) return a > 0 ? '↑ Nuevo este período' : '= Sin cambio';
  const d = Math.round(((a - b) / b) * 100);
  if (d > 0) return `↑${d}% vs período anterior`;
  if (d < 0) return `↓${Math.abs(d)}% vs período anterior`;
  return '= 0% vs período anterior';
}

const deltaTotal = computed(() => deltaTexto(serieTotal.value, (dash.value?.tendencia_anterior ?? []).map(d => d.total)));
const deltaPendientes = computed(() => deltaTexto(seriePendientes.value, (dash.value?.tendencia_pendientes_anterior ?? []).map(d => d.total)));
const deltaEnEspera = computed(() => deltaTexto(serieEnEspera.value, (dash.value?.tendencia_en_espera_anterior ?? []).map(d => d.total)));
const deltaCompletadas = computed(() => deltaTexto(serieCompletadas.value, (dash.value?.tendencia_completadas_anterior ?? []).map(d => d.total)));

/* ── Salud de la cola: gauge + antigüedad + especialidades ── */
/** Antigüedad en cola para la celda de fecha — mismo criterio que los tramos de edad de cola. */
function edadRelativa(s: Solicitud): { texto: string; clase: string } {
  const h = horasDesde(s.created_at);
  if (h < 24) return { texto: `hace ${Math.max(1, Math.round(h))}h`, clase: 'sol-edad--ok' };
  const d = Math.floor(h / 24);
  if (d < 3) return { texto: `hace ${d}d`, clase: 'sol-edad--warn' };
  if (d <= 7) return { texto: `hace ${d}d`, clase: 'sol-edad--alta' };
  return { texto: `hace ${d}d`, clase: 'sol-edad--crit' };
}

function horasDesde(fechaIso: string): number {
  return Math.abs(Date.now() - new Date(fechaIso).getTime()) / 3_600_000;
}

const saludColaPct = computed(() => {
  if (!solicitudes.value.length) return 100;
  const ok = solicitudes.value.filter(s => horasDesde(s.created_at) < 24).length;
  return Math.round((ok / solicitudes.value.length) * 100);
});

/* Antigüedad de la cola: 4 tramos de espera, de fresco a crítico.
   Cada tramo es clicable y filtra la tabla por ese rango de edad. */
const COLA_TRAMOS = [
  { id: 'fresca', label: '< 24h', color: '#22c55e', grad: 'linear-gradient(180deg,#4ade80,#16a34a)', test: (h: number) => h < 24 },
  { id: 'd1_3', label: '1–3 días', color: '#f59e0b', grad: 'linear-gradient(180deg,#fbbf24,#d97706)', test: (h: number) => h >= 24 && h < 72 },
  { id: 'd3_7', label: '3–7 días', color: '#f97316', grad: 'linear-gradient(180deg,#fb923c,#ea580c)', test: (h: number) => h >= 72 && h < 168 },
  { id: 'd7', label: '> 7 días', color: '#ef4444', grad: 'linear-gradient(180deg,#f87171,#dc2626)', test: (h: number) => h >= 168 },
];

const edadCola = ref<string | null>(null);

const colaTramos = computed(() => {
  const total = solicitudes.value.length || 1;
  return COLA_TRAMOS.map(t => {
    const n = solicitudes.value.filter(s => t.test(horasDesde(s.created_at))).length;
    return { ...t, total: n, pct: Math.round((n / total) * 100) };
  });
});

function verTramo(id: string) {
  edadCola.value = edadCola.value === id ? null : id;
  // Los tramos cuentan sobre toda la cola — si el tab de estado queda en
  // 'pendiente' la tabla muestra menos filas de las que promete el tramo.
  // Al elegir uno, el filtro de estado vuelve a 'todas' para que el número
  // del gráfico coincida con lo que se lista.
  if (edadCola.value) {
    tabActiva.value = 'todas';
  }
  paginaActual.value = 1;
}

const masAntiguaTxt = computed(() => {
  if (!solicitudes.value.length) return '—';
  const h = Math.max(...solicitudes.value.map(s => horasDesde(s.created_at)));
  if (h < 1) return `${Math.max(1, Math.round(h * 60))}m`;
  if (h < 24) return `${Math.floor(h)}h`;
  return `${Math.floor(h / 24)}d`;
});

const colaCriticas = computed(() => solicitudes.value.filter(s => horasDesde(s.created_at) >= 24).length);

/* Accesos directos desde el gauge de salud de la cola. */
function verSinGestionar() {
  cambiarTab('pendiente');
}

/** Las más viejas primero — las que ya pasaron las 24h. */
function verCriticas() {
  cambiarTab('todas');
  sortKey.value = 'fecha';
  sortDir.value = 'asc';
}

/** Abre directamente el detalle de la solicitud más antigua de la cola. */
function verMasAntigua() {
  if (!solicitudes.value.length) return;
  const s = solicitudes.value.reduce((a, b) => (horasDesde(a.created_at) > horasDesde(b.created_at) ? a : b));
  verDetalle(s);
}

const espColaColors = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#14b8a6'];
const topEspecialidadesCola = computed(() => {
  const mapa = new Map<string, number>();
  solicitudes.value.forEach(s => {
    const e = s.especialidad_requerida?.trim();
    if (e) mapa.set(e, (mapa.get(e) ?? 0) + 1);
  });
  const tot = solicitudes.value.length || 1;
  return [...mapa.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, 6)
    .map(([nombre, total]) => ({ nombre, total, pct: Math.round((total / tot) * 100) }));
});

const hayFiltros = computed(() =>
  !!filtro.value.buscar || tabActiva.value !== 'todas'
  || filtroClinica.value !== '' || !!filtroEspecialidad.value || !!filtroEps.value || !!rangoFechas.value
  || !!filtroOrigen.value || !!edadCola.value
);
/** Cuántos filtros del panel están activos (el tab de estado no cuenta — ya se ve en los chips). */
const conteoFiltros = computed(() =>
  [filtroClinica.value !== '', !!filtroEspecialidad.value, !!filtroEps.value, !!rangoFechas.value, !!filtro.value.buscar, !!filtroOrigen.value]
    .filter(Boolean).length
);

/* ── Avatar de paciente con color determinístico por nombre ── */
const AVATAR_TONES = [
  { bg: '#dbeafe', fg: '#2563eb' }, { bg: '#dcfce7', fg: '#16a34a' },
  { bg: '#fef3c7', fg: '#d97706' }, { bg: '#ede9fe', fg: '#7c3aed' },
  { bg: '#fce7f3', fg: '#db2777' }, { bg: '#cffafe', fg: '#0891b2' },
  { bg: '#fee2e2', fg: '#dc2626' }, { bg: '#e0e7ff', fg: '#4f46e5' },
];
function avatarStyle(s: Solicitud) {
  const nombre = nombreCompleto(s);
  let h = 0;
  for (const ch of nombre) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  const t = AVATAR_TONES[h % AVATAR_TONES.length];
  return { background: t.bg, color: t.fg };
}

async function cargarStats() {
  try {
    const { data } = await http.get('/api/dashboard/stats');
    dash.value = data.data;
  } catch {
    // El panel lateral es complementario — si falla no interrumpe la tabla.
  }
}

const displayStats = ref([0, 0, 0, 0]);

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

const solicitudesFiltradas = computed(() => {
  const texto = buscarDebounced.value.trim().toLowerCase();
  const cedulaBuscada = /^[\d.\s-]+$/.test(texto) ? texto.replace(/[.\s-]/g, '') : '';
  let result = solicitudes.value.filter(s => {
    if (tabActiva.value !== 'todas' && s.estado !== tabActiva.value) return false;
    if (filtroClinica.value !== '') {
      const f = filtroClinica.value;
      const coincide = typeof f === 'number'
        ? s.clinica_id === f
        : s.institucion_remitente === f || s.clinica?.nombre === f;
      if (!coincide) return false;
    }
    if (filtroEspecialidad.value && s.especialidad_requerida !== filtroEspecialidad.value) return false;
    if (filtroEps.value && s.eps !== filtroEps.value) return false;
    if (filtroOrigen.value && (s.inbox_mensaje_id ? 'correo' : 'aplicativo') !== filtroOrigen.value) return false;
    if (rangoFechas.value) {
      const dia = (s.fecha ?? s.created_at)?.slice(0, 10);
      if (dia && (dia < rangoFechas.value[0] || dia > rangoFechas.value[1])) return false;
    }
    if (edadCola.value) {
      const tramo = COLA_TRAMOS.find(t => t.id === edadCola.value);
      if (tramo && !tramo.test(horasDesde(s.created_at))) return false;
    }
    const documento = String(s.numero_documento ?? '').toLowerCase();
    const coincideTexto = !texto ||
      nombreCompleto(s).toLowerCase().includes(texto) ||
      s.eps.toLowerCase().includes(texto) ||
      s.especialidad_requerida.toLowerCase().includes(texto) ||
      (s.clinica?.nombre ?? s.institucion_remitente ?? '').toLowerCase().includes(texto) ||
      documento.includes(texto) ||
      (cedulaBuscada !== '' && documento.replace(/[.\s-]/g, '').includes(cedulaBuscada));
    return coincideTexto;
  });
  const dir = sortDir.value === 'asc' ? 1 : -1;
  result = [...result].sort((a, b) => {
    let va = '', vb = '';
    if (sortKey.value === 'paciente') { va = nombreCompleto(a).toLowerCase(); vb = nombreCompleto(b).toLowerCase(); }
    else if (sortKey.value === 'clinica') { va = (a.clinica?.nombre ?? a.institucion_remitente ?? '').toLowerCase(); vb = (b.clinica?.nombre ?? b.institucion_remitente ?? '').toLowerCase(); }
    else if (sortKey.value === 'especialidad') { va = (a.especialidad_requerida ?? '').toLowerCase(); vb = (b.especialidad_requerida ?? '').toLowerCase(); }
    else if (sortKey.value === 'eps') { va = (a.eps ?? '').toLowerCase(); vb = (b.eps ?? '').toLowerCase(); }
    else { va = `${a.fecha} ${a.hora ?? ''}`; vb = `${b.fecha} ${b.hora ?? ''}`; }
    return va < vb ? -dir : va > vb ? dir : 0;
  });
  return result;
});

const totalPaginas = computed(() => Math.max(1, Math.ceil(solicitudesFiltradas.value.length / itemsPorPagina.value)));
const solicitudesPaginadas = computed(() => {
  const start = (paginaActual.value - 1) * itemsPorPagina.value;
  return solicitudesFiltradas.value.slice(start, start + itemsPorPagina.value);
});

/** Páginas numeradas con elipsis: 1 2 3 4 5 … 16 */
const paginasVisibles = computed<(number | '…')[]>(() => {
  const total = totalPaginas.value;
  const cur = paginaActual.value;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  if (cur <= 3) return [1, 2, 3, 4, 5, '…', total];
  if (cur >= total - 2) return [1, '…', total - 4, total - 3, total - 2, total - 1, total];
  return [1, '…', cur - 1, cur, cur + 1, '…', total];
});

function cambiarTab(tab: 'todas' | 'pendiente' | 'pendiente_cama' | 'en_espera') {
  tabActiva.value = tab;
  paginaActual.value = 1;
}

const exportando = ref(false);

async function exportarExcel() {
  const rows = solicitudesFiltradas.value;
  try {
    exportando.value = true;
    await exportarSolicitudesExcel(rows.map(s => s.id), 'solicitudes');
    notify.success(`Exportadas ${rows.length} solicitudes a Excel`);
  } catch {
    notify.error('No se pudo generar el Excel.');
  } finally {
    exportando.value = false;
  }
}

function inicialesPaciente(s: Solicitud): string {
  const parts = [s.primer_nombre, s.primer_apellido].filter(Boolean);
  if (parts.length === 0) return '?';
  return parts.map(p => p[0]).join('').toUpperCase().slice(0, 2);
}

function nombreCompleto(s: Solicitud) {
  return [s.primer_nombre, s.segundo_nombre, s.primer_apellido, s.segundo_apellido]
    .filter(Boolean).join(' ');
}

// El código ya puede venir incluido al inicio de dx.descripcion (diagnósticos
// elegidos del catálogo en Nueva Solicitud) — se le quita antes de mostrarlo
// junto al badge del código, para no repetirlo dos veces.
function descripcionSinCodigo(dx: { codigo_cie10: string; descripcion: string }) {
  if (dx.codigo_cie10 && dx.descripcion.startsWith(dx.codigo_cie10)) {
    return dx.descripcion.slice(dx.codigo_cie10.length).replace(/^\s*—\s*/, '');
  }
  return dx.descripcion;
}

function estadoLabel(estado: string) {
  return { pendiente: 'Nueva solicitud', pendiente_cama: 'Pendiente', en_espera: 'Aceptado', completado: 'Completada', negado: 'Negada', cancelado: 'Cancelada' }[estado] ?? estado;
}

/** Qué significa cada estado — tooltip en badges y tabs (pendiente vs
 *  en_espera es la confusión clásica del equipo). */
function estadoTooltip(estado: string): string {
  return {
    todas: 'Toda la cola activa.',
    pendiente: 'Nueva — sin revisar, falta aceptarla o negarla.',
    pendiente_cama: 'Aceptada en espera de cupo — el código se envía cuando haya disponibilidad.',
    en_espera: 'Aceptado — esperando que el paciente llegue a la sede.',
    completado: 'El paciente ya fue atendido.',
    negado: 'La solicitud fue rechazada.',
  }[estado] ?? estado;
}

/** ID interno de la app (distinto del código REF que emite Gomedisys). */
function idInterno(id: number): string {
  return 'ID' + String(id).padStart(6, '0');
}

function formatFecha(fecha: string) {
  if (!fecha) return '—';
  // La columna `fecha` llega serializada como "YYYY-MM-DDT00:00:00.000000Z" —
  // con hora en medianoche es una fecha de calendario, no un instante: se toma
  // la parte de fecha en hora local para no correrla un día hacia atrás.
  const esSoloFecha = !fecha.includes('T') || fecha.slice(11, 19) === '00:00:00';
  const d = new Date(esSoloFecha ? fecha.slice(0, 10) + 'T00:00:00' : fecha);
  if (isNaN(d.getTime())) return fecha;
  return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatHora(hora?: string): string {
  if (!hora) return '';
  const [h, m] = hora.split(':').map(Number);
  if (Number.isNaN(h) || Number.isNaN(m)) return hora;
  const ampm = h >= 12 ? 'p. m.' : 'a. m.';
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
}

function horaDeFecha(fecha: string): string {
  return new Date(fecha).toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
}

interface PasoSeguimiento {
  titulo: string;
  fecha: string;
  hecho: boolean;
  tipo: 'enviada' | 'revision' | 'aceptada' | 'negada' | 'espera' | 'completado' | 'revertido';
}

/** Línea de tiempo real de la solicitud: siempre hay un envío; el resto depende del estado y de los eventos registrados. */
const pasosSeguimiento = computed<PasoSeguimiento[]>(() => {
  const s = solicitudSeleccionada.value;
  if (!s) return [];

  const pasos: PasoSeguimiento[] = [{
    titulo: 'Solicitud enviada',
    fecha: `${formatFecha(s.fecha)} · ${formatHora(s.hora)}`,
    hecho: true,
    tipo: 'enviada',
  }];

  if (s.estado === 'pendiente') {
    // Si ya se había aceptado/negado antes y alguien lo deshizo, esa
    // decisión anterior no debe desaparecer de la vista — se muestra la
    // última reversión antes de "En revisión".
    const eventoRevertido = [...(s.eventos ?? [])].reverse().find(e => e.tipo === 'revertido');
    if (eventoRevertido) {
      pasos.push({
        titulo: 'Devuelta a pendiente',
        fecha: `${formatFecha(eventoRevertido.created_at)} · ${horaDeFecha(eventoRevertido.created_at)}`,
        hecho: true,
        tipo: 'revertido',
      });
    }
    pasos.push({ titulo: 'En revisión', fecha: '—', hecho: false, tipo: 'revision' });
    return pasos;
  }

  const respuestaFecha = s.hora_respuesta
    ? `${formatHora(s.hora_respuesta)}${s.nombre_quien_responde ? ' · ' + s.nombre_quien_responde : ''}`
    : '—';

  if (s.estado === 'negado') {
    pasos.push({ titulo: 'Solicitud negada', fecha: respuestaFecha, hecho: true, tipo: 'negada' });
    return pasos;
  }

  if (s.estado === 'cancelado') {
    pasos.push({ titulo: 'Solicitud cancelada', fecha: respuestaFecha, hecho: true, tipo: 'negada' });
    return pasos;
  }

  pasos.push({ titulo: 'Solicitud aceptada', fecha: respuestaFecha, hecho: true, tipo: 'aceptada' });

  if (s.estado === 'pendiente_cama') {
    // Aceptada en espera de cupo — el paso "en espera" queda pendiente
    // de que haya disponibilidad y se envíe el código a la clínica.
    pasos.push({ titulo: 'En espera de cupo', fecha: '—', hecho: true, tipo: 'espera' });
    pasos.push({ titulo: 'Paciente en espera', fecha: '—', hecho: false, tipo: 'espera' });
    return pasos;
  }

  const eventoEspera = s.eventos?.find(e => e.tipo === 'en_espera');
  if (eventoEspera || s.estado === 'en_espera' || s.estado === 'completado') {
    pasos.push({
      titulo: 'Paciente en espera',
      fecha: eventoEspera ? `${formatFecha(eventoEspera.created_at)} · ${horaDeFecha(eventoEspera.created_at)}` : '—',
      hecho: !!eventoEspera,
      tipo: 'espera',
    });
  }

  const eventoCompletado = s.eventos?.find(e => e.tipo === 'completado');
  if (eventoCompletado || s.estado === 'completado') {
    pasos.push({
      titulo: 'Paciente atendido',
      fecha: eventoCompletado ? `${formatFecha(eventoCompletado.created_at)} · ${horaDeFecha(eventoCompletado.created_at)}` : '—',
      hecho: !!eventoCompletado,
      tipo: 'completado',
    });
  }

  return pasos;
});

const ICONO_PASO: Record<PasoSeguimiento['tipo'], any> = {
  enviada: SendIcon,
  revision: HourglassIcon,
  aceptada: CheckCircleIcon,
  negada: XCircleIcon,
  espera: ClockIcon,
  completado: CheckCircleIcon,
  revertido: RotateCcwIcon,
};

async function exportarPdf() {
  const s = solicitudSeleccionada.value;
  if (!s) return;
  try {
    await exportarSolicitudPdf(s);
  } catch {
    notify.error('No se pudo generar el PDF. Intente de nuevo.');
  }
}

function formatFileSize(bytes: number): string {
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  let size = bytes;
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
  return size.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
}

function isPdf(adj: { mime_type?: string; nombre_original?: string }): boolean {
  const isMimePdf = adj.mime_type === 'application/pdf';
  const isExtPdf = (adj.nombre_original ?? '').toLowerCase().endsWith('.pdf');
  return isMimePdf || isExtPdf;
}

function isImage(adj: { mime_type?: string; nombre_original?: string }): boolean {
  const isMimeImage = (adj.mime_type ?? '').startsWith('image/');
  const isExtImage = /\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(adj.nombre_original ?? '');
  return isMimeImage || isExtImage;
}

function isPreviewable(adj: { mime_type?: string; nombre_original?: string }): boolean {
  return isPdf(adj) || isImage(adj);
}

const isImageAdjunto = computed(() => adjuntoActivo.value ? isImage(adjuntoActivo.value) : false);

function adjuntoUrl(adj: { id: number }): string {
  return `/api/solicitudes-referencia/${solicitudSeleccionada.value?.id}/adjuntos/${adj.id}/descargar`;
}

function formatTamano(bytes: number): string {
  if (!bytes) return '—';
  return bytes >= 1024 * 1024
    ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

const pdfBlobUrl = ref<string | null>(null);
const cargandoPdf = ref(false);
const zoomLevel = ref(1);
const pantallaCompleta = ref(false);

const pdfZoomUrl = computed(() => {
  if (!pdfBlobUrl.value) return undefined;
  return `${pdfBlobUrl.value}#zoom=${Math.round(zoomLevel.value * 100)}`;
});

function zoomIn() {
  zoomLevel.value = Math.min(parseFloat((zoomLevel.value + 0.25).toFixed(2)), 4);
}
function zoomOut() {
  zoomLevel.value = Math.max(parseFloat((zoomLevel.value - 0.25).toFixed(2)), 0.25);
}
function zoomReset() {
  zoomLevel.value = 1;
}
function onWheel(e: WheelEvent) {
  if (e.deltaY < 0) zoomIn();
  else zoomOut();
}
function togglePantallaCompleta() {
  pantallaCompleta.value = !pantallaCompleta.value;
}

async function abrirAdjunto(adj: { id: number; nombre_original: string; mime_type: string; tamano: number }) {
  adjuntoActivo.value = adj;
  modalPdf.value = true;
  zoomReset();
  pantallaCompleta.value = false;
  if (pdfBlobUrl.value) {
    URL.revokeObjectURL(pdfBlobUrl.value);
    pdfBlobUrl.value = null;
  }
  if (!isPreviewable(adj)) return;
  cargandoPdf.value = true;
  try {
    const response = await http.get(adjuntoUrl(adj), { responseType: 'blob' });
    pdfBlobUrl.value = URL.createObjectURL(response.data);
  } catch {
    notify.error('Error al cargar el archivo');
    adjuntoActivo.value = null;
  } finally {
    cargandoPdf.value = false;
  }
}

function limpiarFiltros() {
  filtro.value.buscar = '';
  tabActiva.value = 'todas';
  filtroClinica.value = '';
  filtroEspecialidad.value = '';
  filtroEps.value = '';
  filtroOrigen.value = '';
  rangoFechas.value = null;
  edadCola.value = null;
}

/** Resalta y hace scroll hasta la solicitud referenciada por la notificación de la campana (?resaltar=ID). */
async function aplicarResaltado() {
  const id = Number(route.query.resaltar);
  if (!id) return;

  resaltarId.value = id;
  tabActiva.value = 'todas';
  filtro.value.buscar = '';
  buscarDebounced.value = '';
  paginaActual.value = 1;

  await nextTick();
  document.getElementById(`sol-row-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });

  const { resaltar: _resaltar, ...resto } = route.query;
  router.replace({ query: resto });
}

async function cargar() {
  try {
    cargando.value = true;
    const { data } = await http.get('/api/solicitudes-referencia');
    solicitudes.value = data.data;
    animateCounters([resumen.value.total, resumen.value.pendientes, resumen.value.enEspera, statsSol.value.completadas]);
  } catch {
    notify.error('Error al cargar las solicitudes');
  } finally {
    cargando.value = false;
  }
}

/** Refresco automático de fondo: invisible — no muestra el esqueleto de
 * carga ni anima nada; solo actualiza los datos cuando algo cambió. */
async function cargarSilencioso() {
  try {
    const { data } = await http.get('/api/solicitudes-referencia');
    const cambiados = actualizarSiCambio(solicitudes, data.data);
    if (cambiados.length === 0) return;
    displayStats.value = [resumen.value.total, resumen.value.pendientes, resumen.value.enEspera, statsSol.value.completadas];
  } catch {
    // Refresco de fondo: si falla, se reintenta en el siguiente ciclo sin interrumpir al usuario.
  }
}

const thumbnails = ref<Map<number, string>>(new Map());

function esImagenMime(mime?: string): boolean {
  return !!mime && mime.startsWith('image/');
}

function limpiarThumbnails() {
  thumbnails.value.forEach(url => URL.revokeObjectURL(url));
  thumbnails.value = new Map();
}

async function cargarThumbnails(s: Solicitud) {
  const imagenes = (s.adjuntos ?? []).filter(a => esImagenMime(a.mime_type));
  for (const adj of imagenes) {
    try {
      const { data } = await http.get(adjuntoUrl(adj), { responseType: 'blob' });
      thumbnails.value.set(adj.id, URL.createObjectURL(data));
      thumbnails.value = new Map(thumbnails.value);
    } catch {
      // Si falla la miniatura, se muestra solo la insignia de tipo — no es crítico.
    }
  }
}

function verDetalle(s: Solicitud) {
  limpiarThumbnails();
  solicitudSeleccionada.value = s;
  adjuntoActivo.value = null;
  if (pdfBlobUrl.value) {
    URL.revokeObjectURL(pdfBlobUrl.value);
    pdfBlobUrl.value = null;
  }
  modalDetalle.value = true;
  cargarThumbnails(s);
}

function contarEvoluciones(solicitud: Solicitud): number {
  return (solicitud.eventos ?? []).filter(evento => evento.tipo === 'evolucion').length;
}

function tieneEvoluciones(solicitud: Solicitud): boolean {
  return contarEvoluciones(solicitud) > 0;
}

const respuestaDraft = ref<Record<number, string>>({});
const respondiendoId = ref<number | null>(null);

async function responderEvolucion(evolucionId: number): Promise<void> {
  const sol = solicitudSeleccionada.value;
  const mensaje = (respuestaDraft.value[evolucionId] ?? '').trim();
  if (!sol || !mensaje || respondiendoId.value !== null) return;

  try {
    respondiendoId.value = evolucionId;
    const { data } = await http.post(`/api/solicitudes-referencia/${sol.id}/evoluciones/${evolucionId}/responder`, { mensaje });
    sol.evoluciones = (sol.evoluciones ?? []).map(e => (e.id === evolucionId ? { ...e, ...data.data } : e));
    respuestaDraft.value[evolucionId] = '';
    data.correo_enviado ? notify.success(data.message) : notify.warning(data.message);
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo enviar la respuesta');
  } finally {
    respondiendoId.value = null;
  }
}

function verEvoluciones(solicitud: Solicitud): void {
  solicitudSeleccionada.value = solicitud;
  modalEvoluciones.value = true;
}

function cerrarDetalle() {
  modalDetalle.value = false;
  limpiarThumbnails();
}

function fechaActual(): string {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

function horaActual(): string {
  const now = new Date();
  const h = String(now.getHours()).padStart(2, '0');
  const m = String(Math.floor(now.getMinutes() / 5) * 5).padStart(2, '0');
  return `${h}:${m}`;
}

function manejarAccionFila(comando: string, s: Solicitud) {
  const acciones: Record<string, (s: Solicitud) => void> = {
    aceptar: abrirAceptar,
    ingreso: consultarIngreso,
    negar: abrirNegar,
    cambiar: abrirCambiar,
    nota: abrirNota,
    evolucion: solicitarEvolucion,
  };
  acciones[comando]?.(s);
}

/** Abre el formulario para pedirle por correo a la clínica la evolución actual del paciente (el correo lleva un enlace directo al módulo de Evolución). */
function solicitarEvolucion(s: Solicitud) {
  solicitudSeleccionada.value = s;
  formEvolucion.value = { mensaje: '', correos_extra: [] };
  modalSolicitarEvolucion.value = true;
}

async function enviarSolicitudEvolucion() {
  const s = solicitudSeleccionada.value;
  if (!s || enviandoEvolucion.value) return;

  try {
    enviandoEvolucion.value = true;
    const { data } = await http.post(`/api/solicitudes-referencia/${s.id}/solicitar-evolucion`, {
      mensaje: formEvolucion.value.mensaje.trim() || null,
      correos_extra: formEvolucion.value.correos_extra,
    });
    notify.success(data.message || 'Solicitud de evolución enviada');
    const idx = solicitudes.value.findIndex(x => x.id === s.id);
    if (idx !== -1) solicitudes.value[idx] = { ...solicitudes.value[idx], ...data.data };
    modalSolicitarEvolucion.value = false;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo enviar la solicitud de evolución');
  } finally {
    enviandoEvolucion.value = false;
  }
}

async function consultarIngreso(s: Solicitud) {
  if (consultandoIngresoId.value !== null) return;
  consultandoIngresoId.value = s.id;
  try {
    const { data } = await http.post(`/api/solicitudes-referencia/${s.id}/consultar-ingreso`);
    // El modal abre siempre: si Gomedisys tiene el ingreso muestra la
    // comparación y precarga el número; si no, permite registrarlo manual
    // o marcar por qué el paciente no llegó.
    solicitudSeleccionada.value = s;
    datosIngresoGomedisys.value = data.ingreso ? data.paciente : null;
    numeroIngresoManual.value = data.paciente?.admission_number ? String(data.paciente.admission_number) : '';
    motivoNoIngreso.value = '';
    correosExtraIngreso.value = [];
    modalConfirmarIngreso.value = true;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo consultar el ingreso en Gomedisys');
  } finally {
    consultandoIngresoId.value = null;
  }
}

/** Completa la solicitud con el número de ingreso — el de Gomedisys precargado o uno escrito manual. */
async function confirmarIngresoPaciente() {
  const s = solicitudSeleccionada.value;
  const numero = Number(numeroIngresoManual.value.trim());
  if (!s) return;
  if (!numero || !Number.isInteger(numero) || numero <= 0) {
    notify.warning('Escribe el número de ingreso (el de Gomedisys o el manual)');
    return;
  }
  try {
    procesandoIngreso.value = true;
    const { data } = await http.post(`/api/solicitudes-referencia/${s.id}/completado`, {
      numero_ingreso: numero,
      correos_extra: correosExtraIngreso.value,
    });
    notify.success('Solicitud completada');
    const idx = solicitudes.value.findIndex(x => x.id === s.id);
    if (idx !== -1) solicitudes.value[idx] = data.data;
    modalConfirmarIngreso.value = false;
    datosIngresoGomedisys.value = null;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'Error al completar la solicitud');
  } finally {
    procesandoIngreso.value = false;
  }
}

/** Motivos por los que el paciente esperado nunca llegó a la institución —
   la referencia se cierra (negada) con esta razón en `motivo_no_ingreso`. */
const MOTIVOS_NO_INGRESO = [
  'N/A',
  'Alta Médica',
  'Alta Voluntaria',
  'Desestimiento',
  'EPS Autoriza Estancia',
  'Fallecimiento',
  'Médico cancela trámite',
  'No trasladable',
  'Otros Motivos',
  'Silencio Administrativo',
  'Superó tiempo de respuesta',
  'Ubicado en Otra IPS',
  'Direccionamiento CAC',
];
const numeroIngresoManual = ref('');
const motivoNoIngreso = ref('');
const correosExtraIngreso = ref<string[]>([]);

/** Correo al que se notifica la clínica (registrado en la clínica o el de contacto de la solicitud). */
const correoDestinoClinica = computed(() =>
  solicitudSeleccionada.value?.clinica?.email
  || solicitudSeleccionada.value?.correo_contacto
  || 'La clínica no tiene correo registrado');

/** Registra que el paciente en espera nunca llegó — cierra la referencia con el motivo elegido. */
async function registrarNoIngreso() {
  const s = solicitudSeleccionada.value;
  if (!s || !motivoNoIngreso.value) {
    notify.warning('Seleccione el motivo de no ingreso');
    return;
  }
  try {
    procesandoIngreso.value = true;
    const { data } = await http.post(`/api/solicitudes-referencia/${s.id}/no-ingreso`, {
      motivo_no_ingreso: motivoNoIngreso.value,
      correos_extra: correosExtraIngreso.value,
    });
    notify.success(data.message || 'Se registró que el paciente no llegó');
    // Sale de la cola activa (queda negada) — se quita de la lista.
    const idx = solicitudes.value.findIndex(x => x.id === s.id);
    if (idx !== -1) solicitudes.value.splice(idx, 1);
    modalConfirmarIngreso.value = false;
    datosIngresoGomedisys.value = null;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo registrar el motivo de no ingreso');
  } finally {
    procesandoIngreso.value = false;
  }
}

const verificandoTodosIngresos = ref(false);

/** Revisa el ingreso en Gomedisys de todas las solicitudes en espera de una vez, en vez de una por una. */
async function verificarTodosIngresos() {
  if (verificandoTodosIngresos.value) return;
  const lote = solicitudesEnEspera.value;
  if (lote.length === 0) return;

  verificandoTodosIngresos.value = true;
  try {
    const resultados = await Promise.allSettled(
      lote.map(s => http.post(`/api/solicitudes-referencia/${s.id}/consultar-ingreso`)),
    );
    const ingresaron = resultados.filter(r => r.status === 'fulfilled' && r.value.data.ingreso).length;
    const fallidas = resultados.filter(r => r.status === 'rejected').length;

    if (ingresaron > 0) {
      notify.success(`${ingresaron} paciente(s) ingresaron y se marcaron como completadas`);
    } else {
      notify.info('Ningún paciente en espera tiene ingreso registrado todavía en Gomedisys');
    }
    if (fallidas > 0) {
      notify.error(`${fallidas} consulta(s) fallaron y no se pudieron verificar`);
    }
    await cargar();
  } finally {
    verificandoTodosIngresos.value = false;
  }
}

function abrirAceptar(s: Solicitud) {
  solicitudSeleccionada.value = s;
  formAceptar.value = {
    fecha_respuesta: fechaActual(),
    hora_respuesta: horaActual(),
    nombre_quien_responde: authStore.user?.full_name ?? '',
    sin_disponibilidad: false,
    sede: s.sede || '',
    eps: EPS_LIST.includes(s.eps) ? s.eps : '',
    codigo_aceptacion: '',
    hora_llegada: '',
    lugar_llegada: s.lugar_llegada || '',
    observaciones_respuesta: '',
    correos_extra: [],
  };
  soportesAceptar.value = [];
  modalAceptar.value = true;
}

interface FichaCampo { label: string; valor: string }

// Campos en el mismo orden del formulario "Registro de Referencia" de
// Gomedisys — así el operador va copiando y pegando de arriba abajo sin
// saltarse nada.
const fichaGomedisys = computed<FichaCampo[]>(() => {
  const s = solicitudSeleccionada.value;
  if (!s) return [];
  const dx = s.diagnosticos?.[0];
  return [
    // No es un campo del formulario — es la sede que hay que seleccionar en
    // Gomedisys ANTES de registrar (si pide "Asignar sede de trabajo").
    { label: 'Sede de trabajo', valor: formAceptar.value.sede || s.sede || 'URGENCIAS' },
    { label: 'Tipo de documento', valor: s.tipo_documento },
    { label: 'Número de documento', valor: s.numero_documento },
    { label: 'Nombres', valor: [s.primer_nombre, s.segundo_nombre].filter(Boolean).join(' ') },
    { label: 'Apellidos', valor: [s.primer_apellido, s.segundo_apellido].filter(Boolean).join(' ') },
    { label: 'Edad', valor: String(s.edad) },
    { label: 'Género', valor: s.genero === 'M' ? 'Masculino' : 'Femenino' },
    { label: 'Diagnóstico CIE-10', valor: dx ? `${dx.codigo_cie10} — ${dx.descripcion}` : s.diagnostico },
    { label: 'Municipio', valor: s.municipio_capita },
    { label: 'Dirección', valor: s.direccion_paciente || '' },
    { label: 'Teléfono', valor: s.telefono_paciente || '' },
    { label: 'Servicio de remisión', valor: s.servicio_remision || s.servicio_ubicacion_actual },
    { label: 'Resumen clínico', valor: s.resumen_historia_clinica },
  ];
});

async function abrirGomedisys(s: Solicitud) {
  // Seguro extra — si llega un segundo clic mientras el primero sigue en
  // curso (doble clic rápido), se ignora.
  if (enviandoGomedisysId.value !== null) return;
  // La sede decide en qué "sede de trabajo" de Gomedisys queda registrada
  // la referencia — si no se ha elegido todavía en el modal, se frena en
  // vez de caer al valor por defecto y registrarla en la sede equivocada.
  const sede = formAceptar.value.sede || s.sede || '';
  if (modalAceptar.value && !sede) {
    notify.warning('Primero selecciona la sede (Urgencias o Torre) — ahí quedará registrada la referencia en Gomedisys.');
    return;
  }
  enviandoGomedisysId.value = s.id;
  solicitudSeleccionada.value = s;
  try {
    const resultado = await autocompletarEnGomedisys({ ...s, sede });
    if (resultado.ok) {
      notify.success(`El formulario quedó diligenciado en la ventana de Gomedisys (sede ${sede || 'URGENCIAS'}) — revísalo allá antes de guardar.`);
      return;
    }
    if (resultado.error === 'login') {
      // La ventana gestionada quedó abierta en el login — la persona entra
      // una sola vez (la sesión queda guardada) y reintenta el botón.
      notify.warning('Se abrió una ventana de Gomedisys — inicia sesión ahí una sola vez y vuelve a darle clic al botón.');
      return;
    }
    // 'local-offline' = el servidor local no está corriendo: sin escándalo,
    // se abre la ficha de copiado manual. Otros errores sí se avisan.
    if (resultado.error && resultado.error !== 'local-offline') {
      notify.warning(resultado.error);
    }
    campoCopiado.value = null;
    modalFichaGomedisys.value = true;
  } finally {
    enviandoGomedisysId.value = null;
  }
}

// navigator.clipboard solo existe en contextos seguros (https/localhost);
// en http://referencia.test toca el fallback con textarea + execCommand.
async function copiarTexto(texto: string): Promise<boolean> {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(texto);
      return true;
    }
  } catch { /* sigue al fallback */ }
  const ta = document.createElement('textarea');
  ta.value = texto;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  const ok = document.execCommand('copy');
  ta.remove();
  return ok;
}

let copiadoTimer: number | undefined;

async function copiarDato(campo: FichaCampo) {
  if (!campo.valor) return;
  if (await copiarTexto(campo.valor)) {
    campoCopiado.value = campo.label;
    window.clearTimeout(copiadoTimer);
    copiadoTimer = window.setTimeout(() => { campoCopiado.value = null; }, 1600);
  } else {
    notify.error('No se pudo copiar al portapapeles');
  }
}

async function copiarTodoGomedisys() {
  const texto = fichaGomedisys.value.map(c => `${c.label}: ${c.valor || '—'}`).join('\n');
  if (await copiarTexto(texto)) {
    notify.success('Todos los datos copiados al portapapeles');
  } else {
    notify.error('No se pudo copiar al portapapeles');
  }
}

function abrirCambiar(s: Solicitud) {
  solicitudSeleccionada.value = s;
  formCambiar.value = {
    fecha_respuesta: fechaActual(),
    hora_respuesta: horaActual(),
    nombre_quien_responde: authStore.user?.full_name ?? '',
    estado: '',
    motivo: '',
    sede: s.sede || '',
    codigo_aceptacion: '',
    hora_llegada: '',
    lugar_llegada: s.lugar_llegada || '',
    numero_ingreso: '',
    eps: EPS_LIST.includes(s.eps) ? s.eps : '',
    correos_extra: [],
    grupo_negacion: '',
    motivo_negacion: '',
  };
  modalCambiar.value = true;
}

async function cambiarEstado() {
  const f = formCambiar.value;
  if (!f.hora_respuesta || !f.estado || !f.motivo.trim() || !f.nombre_quien_responde.trim()
    || (['en_espera', 'pendiente_cama', 'negado'].includes(f.estado) && !f.eps)
    || (f.estado === 'negado' && (!f.grupo_negacion || !f.motivo_negacion.trim()))
    || (f.estado === 'en_espera' && (!f.codigo_aceptacion.trim() || !f.hora_llegada || !f.lugar_llegada.trim() || !f.sede))
    || (f.estado === 'completado' && !String(f.numero_ingreso).trim())) {
    notify.warning('Complete los campos obligatorios');
    return;
  }
  try {
    procesando.value = true;
    const { data } = await http.post(`/api/solicitudes-referencia/${solicitudSeleccionada.value!.id}/cambiar-estado`, f);
    notify.success(data.message || 'Estado actualizado');
    const idx = solicitudes.value.findIndex(s => s.id === solicitudSeleccionada.value!.id);
    if (idx !== -1) {
      // Cancelada sale de la cola activa — pasa al Histórico.
      if (data.data.estado === 'cancelado') solicitudes.value.splice(idx, 1);
      else solicitudes.value[idx] = data.data;
    }
    modalCambiar.value = false;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'Error al cambiar el estado');
  } finally {
    procesando.value = false;
  }
}

/* ── Notas de seguimiento ── */
const modalNota = ref(false);
const notaTexto = ref('');
const guardandoNota = ref(false);

function abrirNota(s: Solicitud) {
  solicitudSeleccionada.value = s;
  notaTexto.value = '';
  modalNota.value = true;
}

async function guardarNota() {
  if (!notaTexto.value.trim()) {
    notify.warning('Escribe la nota de seguimiento');
    return;
  }
  try {
    guardandoNota.value = true;
    const { data } = await http.post(`/api/solicitudes-referencia/${solicitudSeleccionada.value!.id}/notas`, { nota: notaTexto.value.trim() });
    notify.success(data.message || 'Nota agregada');
    const idx = solicitudes.value.findIndex(s => s.id === solicitudSeleccionada.value!.id);
    if (idx !== -1) solicitudes.value[idx] = data.data;
    solicitudSeleccionada.value = data.data;
    modalNota.value = false;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'Error al guardar la nota');
  } finally {
    guardandoNota.value = false;
  }
}

/** Evoluciones clínicas, de la más reciente a la más antigua. */
/** Evoluciones de la clínica con sus soportes adjuntos; si la fila no las trae cargadas, se reconstruyen desde el historial de eventos. */
const evolucionesPaciente = computed(() => {
  const sol = solicitudSeleccionada.value;
  if (!sol) return [];

  if (sol.evoluciones?.length) {
    return sol.evoluciones
      .slice()
      .sort((a, b) => b.created_at.localeCompare(a.created_at))
      .map(e => ({ id: e.id, texto: e.nota, created_at: e.created_at, adjuntos: e.adjuntos ?? [], respuestas: e.respuestas ?? [], responder: true }));
  }

  return (sol.eventos ?? [])
    .filter(e => e.tipo === 'evolucion')
    .sort((a, b) => b.created_at.localeCompare(a.created_at))
    .map(e => ({ id: e.id, texto: e.descripcion, created_at: e.created_at, adjuntos: [] as { id: number; nombre_original: string; mime_type: string; tamano: number }[], respuestas: [] as RespuestaEvolucion[], responder: false }));
});

/** Notas internas de seguimiento, de la más reciente a la más antigua. */
const notasSeguimiento = computed(() =>
  (solicitudSeleccionada.value?.eventos ?? [])
    .filter(e => e.tipo === 'nota')
    .slice()
    .sort((a, b) => b.created_at.localeCompare(a.created_at))
);

function abrirNegar(s: Solicitud) {
  solicitudSeleccionada.value = s;
  formNegar.value = {
    fecha_respuesta: fechaActual(),
    hora_respuesta: horaActual(),
    grupo_negacion: '',
    motivo_negacion: '',
    eps: EPS_LIST.includes(s.eps) ? s.eps : '',
    nombre_quien_responde: authStore.user?.full_name ?? '',
    observaciones_respuesta: '',
    correos_extra: [],
  };
  soportesNegar.value = [];
  modalNegar.value = true;
}

function agregarSoportes(destino: Ref<File[]>, event: Event): void {
  const input = event.target as HTMLInputElement;
  const archivos = [...(input.files ?? [])];
  input.value = '';
  for (const archivo of archivos) {
    if (destino.value.length >= 5) {
      notify.warning('Máximo 5 soportes');
      break;
    }
    if (archivo.size > 10 * 1024 * 1024) {
      notify.warning(`"${archivo.name}" supera los 10 MB`);
      continue;
    }
    destino.value.push(archivo);
  }
}

const agregarSoportesNegar = (event: Event) => agregarSoportes(soportesNegar, event);
const agregarSoportesAceptar = (event: Event) => agregarSoportes(soportesAceptar, event);

async function aceptar() {
  if (!formAceptar.value.hora_respuesta || !formAceptar.value.nombre_quien_responde.trim() || !formAceptar.value.eps || !formAceptar.value.lugar_llegada.trim()
    || (!formAceptar.value.sin_disponibilidad && (!formAceptar.value.sede || !formAceptar.value.codigo_aceptacion.trim() || !formAceptar.value.hora_llegada))) {
    notify.warning('Complete los campos obligatorios');
    return;
  }
  try {
    procesando.value = true;
    const body = new FormData();
    Object.entries(formAceptar.value).forEach(([campo, valor]) => {
      if (Array.isArray(valor)) {
        valor.forEach((v) => body.append(`${campo}[]`, String(v)));
      } else {
        body.append(campo, typeof valor === 'boolean' ? (valor ? '1' : '0') : String(valor ?? ''));
      }
    });
    soportesAceptar.value.forEach((soporte) => body.append('soportes[]', soporte));
    const { data } = await http.post(`/api/solicitudes-referencia/${solicitudSeleccionada.value!.id}/aceptar`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    notify.success(data.message || 'Solicitud aceptada');
    const idx = solicitudes.value.findIndex(s => s.id === solicitudSeleccionada.value!.id);
    if (idx !== -1) solicitudes.value[idx] = data.data;
    modalAceptar.value = false;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'Error al aceptar la solicitud');
  } finally {
    procesando.value = false;
  }
}

async function negar() {
  if (!formNegar.value.hora_respuesta || !formNegar.value.grupo_negacion || !formNegar.value.motivo_negacion.trim() || !formNegar.value.eps || !formNegar.value.nombre_quien_responde.trim()) {
    notify.warning('Complete los campos obligatorios');
    return;
  }
  try {
    procesando.value = true;
    const body = new FormData();
    Object.entries(formNegar.value).forEach(([campo, valor]) => {
      if (Array.isArray(valor)) {
        valor.forEach((v) => body.append(`${campo}[]`, String(v)));
      } else {
        body.append(campo, valor ?? '');
      }
    });
    soportesNegar.value.forEach((soporte) => body.append('soportes[]', soporte));
    const { data } = await http.post(`/api/solicitudes-referencia/${solicitudSeleccionada.value!.id}/negar`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    notify.success('Solicitud negada');
    const idx = solicitudes.value.findIndex(s => s.id === solicitudSeleccionada.value!.id);
    if (idx !== -1) solicitudes.value[idx] = data.data;
    modalNegar.value = false;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'Error al negar la solicitud');
  } finally {
    procesando.value = false;
  }
}


onMounted(async () => {
  await Promise.all([cargar(), cargarStats()]);
  await aplicarResaltado();
});

// Re-aplica el resaltado cada vez que llega un nuevo ?resaltar=ID por la
// campana de notificaciones, aunque ya estemos en esta misma ruta (un
// cambio de query no vuelve a montar el componente, así que onMounted solo
// no alcanza para notificaciones repetidas).
watch(() => route.query.resaltar, (nuevo) => {
  if (nuevo) aplicarResaltado();
});

usePolling(() => {
  if (!cargando.value && !modalDetalle.value && !modalAceptar.value && !modalNegar.value && !modalPdf.value && !modalFichaGomedisys.value && !modalConfirmarIngreso.value && !modalNota.value && !modalCambiar.value) cargarSilencioso();
}, 30000);
</script>

<style scoped>
.ph-solicitudes {
  background:
    radial-gradient(ellipse at 4% 0%, rgba(88, 142, 211, .12), transparent 34%),
    linear-gradient(160deg, #f4f7fb 0%, #edf2f8 52%, #f6f8fb 100%);
}
.dark .ph-solicitudes {
  background:
    radial-gradient(ellipse at 4% 0%, rgba(59, 130, 246, .12), transparent 34%),
    linear-gradient(160deg, #111827 0%, #151d2b 52%, #111827 100%);
}

/* ── Stat cards ── */
.sol-stats-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: .65rem;
}
.sol-stats-bar :deep(.stat-card-pastel) {
  min-height: 70px;
  border-radius: 14px !important;
  box-shadow: 0 3px 12px rgba(24, 54, 94, .07);
}
.sol-stats-bar :deep(.stat-card-pastel:hover) {
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(24, 54, 94, .12);
}
.sol-stats-bar :deep(.stat-pastel-icon) {
  width: 36px;
  height: 36px;
  border-radius: 10px;
}
.sol-stats-bar :deep(.stat-pastel-value) { font-size: 22px; }
.sol-stats-bar :deep(.stat-pastel-label) { font-size: 9px; }
.sol-stats-bar :deep(.stat-pastel-sparkline) {
  right: 10px;
  bottom: 8px;
  opacity: .65;
}
@media (max-width: 900px) {
  .sol-stats-bar { grid-template-columns: repeat(2, 1fr); }
}

/* ── Tab dots ── */
.sol-tab-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  display: inline-block;
  margin-right: .3rem;
}
.sol-tab-dot--pendiente { background: #f59e0b; }
.sol-tab-dot--pendiente_cama { background: #f97316; }
.sol-tab-dot--en_espera { background: #3b82f6; }
.sol-tab-dot--completado { background: #22c55e; }
.sol-tab-dot--negado { background: #ef4444; }

/* ── Transición del panel de filtros ── */
.bulk-slide-enter-active, .bulk-slide-leave-active {
  transition: all .25s ease;
}
.bulk-slide-enter-from, .bulk-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ── Sortable headers ── */
.sol-th-sortable {
  cursor: pointer;
  user-select: none;
  transition: color .15s ease;
}
.sol-th-sortable:hover { color: #16468E; }

/* ── Fila resaltada (llegó desde una notificación) ── */
.sol-table-row-resaltada {
  animation: sol-row-glow 2.2s ease-in-out 2;
  box-shadow: inset 3px 0 0 #D97706;
}
@keyframes sol-row-glow {
  0%, 100% { background: transparent; }
  50% { background: rgba(217, 119, 6, .12); }
}
.sol-table-name-resaltada {
  text-decoration: underline;
  text-decoration-color: #D97706;
  text-decoration-thickness: 2px;
  text-underline-offset: 3px;
}

/* ── Row transitions ── */
.sol-row-enter-active, .sol-row-leave-active {
  transition: all .3s ease;
}
.sol-row-enter-from {
  opacity: 0;
  transform: translateX(-12px);
}
.sol-row-leave-to {
  opacity: 0;
  transform: translateX(12px);
}

/* ── Pagination ── */
.sol-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .45rem 0;
  flex-shrink: 0;
  gap: 10px;
  flex-wrap: wrap;
  background: transparent;
}
.dark .sol-pagination { background: transparent; }
.sol-pagination-info {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}
.sol-pagination-controls {
  display: flex;
  align-items: center;
  gap: .4rem;
}
.sol-pagination-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px; height: 28px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  transition: all .15s ease;
}
.sol-pagination-btn:hover:not(:disabled) {
  border-color: #16468E;
  color: #16468E;
  background: #f0f5ff;
}
.sol-pagination-btn:disabled {
  opacity: .4;
  cursor: not-allowed;
}
.sol-pagination-page {
  font-size: 12px;
  font-weight: 600;
  color: #334e70;
  min-width: 60px;
  text-align: center;
}
.sol-page-num { font-size: 11px; font-weight: 700; font-family: inherit; }
.sol-page-active {
  background: linear-gradient(135deg, #0D2D6B, #16468E) !important;
  border-color: #16468E !important;
  color: #fff !important;
  box-shadow: 0 3px 8px rgba(13,45,107,.3);
}
.sol-page-ellipsis { font-size: 11px; color: #94a3b8; padding: 0 2px; }
.sol-per-page { width: 110px; }
.sol-per-page :deep(.el-select__wrapper) { min-height: 26px; font-size: 11px; }

/* ── Panel lateral derecho (todo cabe sin scroll: top clínicas toma el resto) ── */
.sol-side {
  width: 300px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow: hidden;
  min-height: 0;
  padding-bottom: 2px;
}
@media (max-width: 1050px) { .sol-side { display: none; } }
.sol-side-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(22,70,142,.08);
  padding: 9px 12px;
  flex-shrink: 0;
}
.sol-side-panel-flex {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.sol-side-panel-flex .sol-esp-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  scrollbar-width: thin;
}
.dark .sol-side-panel { background: #161b28; border-color: rgba(255,255,255,0.07); }
.sol-side-head {
  display: flex; align-items: center; gap: 6px;
  margin: -2px -2px 8px;
  padding: 7px 10px;
  border-radius: 9px;
  background: #16468E;
  border: 1px solid #123a78;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.12), 0 3px 12px rgba(13,45,107,.22);
}
.sol-side-icon { color: rgba(255,255,255,.9); flex-shrink: 0; }
.sol-side-title {
  font-size: 11.5px; font-weight: 800; color: #fff;
  margin: 0; flex: 1; min-width: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  letter-spacing: .02em; text-shadow: 0 1px 2px rgba(0,0,0,.25);
}
.sol-side-pill {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 9px; font-weight: 700; color: rgba(255,255,255,.8);
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.16);
  padding: 2px 7px; border-radius: 999px; white-space: nowrap;
}

/* ── Gauge salud de la cola ── */
.sol-side-pill-live { color: #86efac; background: rgba(34,197,94,.15); border-color: rgba(74,222,128,.25); }
.sol-live-dot {
  width: 5px; height: 5px; border-radius: 50%;
  background: #22c55e;
  animation: solLivePulse 1.6s ease-in-out infinite;
}
@keyframes solLivePulse {
  0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
  50% { opacity: .7; box-shadow: 0 0 0 3px rgba(34,197,94,0); }
}
/* ── Salud de la cola: resumen + barra segmentada por antigüedad ── */
.cola-resumen {
  display: flex; align-items: center; gap: 9px;
  margin-bottom: 9px;
}
.cola-resumen-num {
  font-size: 26px; font-weight: 800; color: #1e293b; line-height: 1;
  background: linear-gradient(135deg, #16468e, #2563eb);
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.dark .cola-resumen-num { background: linear-gradient(135deg, #93c5fd, #60a5fa); -webkit-background-clip: text; background-clip: text; }
.cola-resumen-txt { display: flex; flex-direction: column; gap: 3px; }
.cola-resumen-label { font-size: 8.5px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; }
.cola-resumen-badge {
  align-self: flex-start;
  font-size: 8.5px; font-weight: 800; padding: 2px 7px; border-radius: 999px;
}
.cola-resumen-badge--ok { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.cola-resumen-badge--warn { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.dark .cola-resumen-badge--ok { background: rgba(34,197,94,.14); color: #4ade80; border-color: rgba(34,197,94,.3); }
.dark .cola-resumen-badge--warn { background: rgba(245,158,11,.14); color: #fbbf24; border-color: rgba(245,158,11,.3); }

/* Barra segmentada verde→roja según cuánto lleva cada solicitud esperando */
.cola-bar {
  display: flex; gap: 3px;
  height: 12px; width: 100%;
  margin-bottom: 10px;
}
.cola-seg {
  height: 100%; min-width: 3px;
  border-radius: 4px;
  cursor: pointer;
  transition: width .6s cubic-bezier(.22,1,.36,1), transform .15s ease, filter .15s ease, opacity .2s ease;
}
.cola-seg:hover { transform: scaleY(1.25); filter: brightness(1.05); }
.cola-seg--on { outline: 2px solid rgba(22,70,142,.35); outline-offset: 1px; }
.dark .cola-seg--on { outline-color: rgba(147,197,253,.5); }

/* Filas-leyenda clicables por tramo */
.cola-rows { display: flex; flex-direction: column; gap: 5px; margin-bottom: 8px; }
.cola-row {
  display: flex; align-items: center; gap: 7px;
  width: 100%;
  background: transparent; border: 1px solid transparent; border-radius: 7px;
  padding: 3px 5px; cursor: pointer;
  transition: background .15s ease, border-color .15s ease;
}
.cola-row:hover { background: #f1f5f9; }
.dark .cola-row:hover { background: rgba(255,255,255,0.05); }
.cola-row--on { background: #eef4ff; border-color: #bfdbfe; }
.dark .cola-row--on { background: rgba(59,130,246,0.12); border-color: rgba(96,165,250,0.35); }
.cola-row-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.cola-row-label { font-size: 9.5px; font-weight: 700; color: #475569; min-width: 44px; text-align: left; }
.dark .cola-row-label { color: #cbd5e1; }
.cola-row-track {
  flex: 1; height: 5px; border-radius: 3px;
  background: #eef2f7; overflow: hidden;
}
.dark .cola-row-track { background: rgba(255,255,255,0.08); }
.cola-row-track i {
  display: block; height: 100%; border-radius: 3px;
  transition: width .6s cubic-bezier(.22,1,.36,1);
}
.cola-row-num { font-size: 10.5px; font-weight: 800; color: #1e293b; min-width: 22px; text-align: right; }
.dark .cola-row-num { color: #e2e8f0; }

.sol-gauge-stats {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 6px; margin-top: 4px;
}
.sol-gauge-stat {
  text-align: center; padding: 5px 2px;
  background: #f8fafc; border: 1px solid #eef2f7; border-radius: 8px;
}
.dark .sol-gauge-stat { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.07); }
.sol-gauge-stat--link { cursor: pointer; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
.sol-gauge-stat--link:hover { background: #eef4ff; border-color: #bfdbfe; transform: translateY(-1px); }
.dark .sol-gauge-stat--link:hover { background: rgba(59,130,246,0.12); border-color: rgba(96,165,250,0.35); }
.sol-gauge-stat-num { display: block; font-size: 12px; font-weight: 800; color: #1e293b; line-height: 1; }
.dark .sol-gauge-stat-num { color: #e2e8f0; }
.sol-gauge-stat-warn { color: #dc2626; }
.dark .sol-gauge-stat-warn { color: #f87171; }
.sol-gauge-stat-label { font-size: 8px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }

/* ── Especialidades en cola ── */
.sol-esp-list { display: flex; flex-direction: column; gap: 12px; justify-content: space-evenly; }
.sol-esp-row { display: flex; align-items: center; gap: 9px; }
.sol-esp-rank {
  width: 20px; height: 20px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 9.5px; font-weight: 800;
  background: #f1f5f9; color: #64748b; flex-shrink: 0;
}
.sol-esp-rank--1 { background: #fef3c7; color: #b45309; }
.sol-esp-rank--2 { background: #e2e8f0; color: #475569; }
.sol-esp-rank--3 { background: #ffedd5; color: #c2410c; }
.sol-esp-info { flex: 1; min-width: 0; }
.sol-esp-name {
  font-size: 11px; font-weight: 700; color: #334155;
  margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .sol-esp-name { color: #cbd5e1; }
.sol-esp-bar {
  height: 7px; border-radius: 999px; background: #f1f5f9; overflow: hidden;
}
.dark .sol-esp-bar { background: rgba(255,255,255,0.07); }
.sol-esp-fill {
  height: 100%; border-radius: 999px;
  transition: width .6s cubic-bezier(.22,1,.36,1);
  box-shadow: 0 1px 4px rgba(0,0,0,.15);
}
.sol-esp-total {
  flex-shrink: 0; min-width: 24px; text-align: right;
  font-size: 12px; font-weight: 800; color: #1e293b;
}
.dark .sol-esp-total { color: #e2e8f0; }
.sol-esp-empty { font-size: 10px; color: #94a3b8; text-align: center; margin: 12px 0; }

/* ── Modal: Visor de adjunto ── */
:deep(.pdf-dialog) { border-radius: 16px; overflow: hidden; box-shadow: 0 32px 80px rgba(11,35,73,.35); transition: width .3s ease, max-width .3s ease; }
:deep(.pdf-dialog.pdf-fullscreen) { width: 100% !important; max-width: 100vw !important; margin: 0 !important; height: 100vh; border-radius: 0; }
:deep(.pdf-dialog.pdf-fullscreen .el-dialog__body) { height: calc(100vh - 52px); }
:deep(.pdf-dialog .el-dialog__header) { margin: 0; padding: .75rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
:deep(.pdf-dialog .el-dialog__body) {
  padding: 0;
}
:deep(.pdf-dialog .el-dialog__headerbtn) { top: 10px; right: 12px; width: 28px; height: 28px; }
.pdf-dialog-header {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pdf-dialog-header > svg {
  color: #64748b;
}
.pdf-dialog-title {
  flex: 1;
  min-width: 0;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pdf-dialog-download {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 600;
  color: #2563eb;
  background: #dbeafe;
  padding: 6px 12px;
  border-radius: 8px;
  text-decoration: none;
  transition: background .2s ease;
}
.pdf-dialog-download:hover {
  background: #bfdbfe;
}
.pdf-viewer-wrap {
  height: 75vh;
  min-height: 420px;
  background: #f1f5f9;
  position: relative;
  overflow: auto;
}
.pdf-viewer-wrap.pdf-viewer-fullscreen {
  height: calc(100vh - 52px);
}
.pdf-zoom-toolbar {
  position: absolute;
  bottom: 16px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(13, 45, 107, .9);
  backdrop-filter: blur(8px);
  padding: 6px 8px;
  border-radius: 999px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, .25);
}
.pdf-zoom-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background: transparent;
  color: #fff;
  cursor: pointer;
  transition: background .2s ease;
}
.pdf-zoom-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, .2);
}
.pdf-zoom-btn:disabled {
  opacity: .35;
  cursor: not-allowed;
}
.pdf-zoom-label {
  min-width: 48px;
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  user-select: none;
}
.pdf-viewer-loading {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #5b7aa8;
  font-size: 13px;
  font-weight: 500;
}
.pdf-viewer-iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}
.pdf-viewer-image-wrap {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: auto;
  padding: 24px;
}
.pdf-viewer-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: 10px;
  box-shadow: 0 12px 32px rgba(13, 45, 107, .2);
  border: 4px solid #fff;
}
.pdf-viewer-fallback {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  color: #7c93b8;
  font-size: 13px;
  font-weight: 500;
}
.pdf-viewer-fallback svg {
  color: #b6c6e0;
}
.pdf-viewer-download-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, #0D2D6B 0%, #16468E 100%);
  padding: 9px 20px;
  border-radius: 999px;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(13,45,107,.25);
  transition: transform .2s ease, box-shadow .2s ease;
}
.pdf-viewer-download-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 22px rgba(13,45,107,.32);
}

/* ── Diagnóstico column ── */
.sol-dx-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
  max-width: 220px;
}
.sol-dx-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sol-dx-tag strong {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 800;
  color: #6d28d9;
  background: #ede9fe;
  padding: 1px 5px;
  border-radius: 4px;
  letter-spacing: .02em;
}
.sol-dx-desc {
  color: #64748b;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sol-dx-more {
  font-size: 10px;
  font-weight: 700;
  color: #7c3aed;
  padding-left: 2px;
}
.sol-dx-text {
  font-size: 11px;
  color: #64748b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sol-dx-none {
  color: #cbd5e1;
  font-size: 12px;
}

.sol-header-spacer { flex: 1; }

/* ── Acciones rápidas junto a los chips ── */
.sol-chips-actions { display: flex; align-items: center; gap: 4px; margin-right: 4px; }
.sol-icon-btn {
  width: 30px; height: 30px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  background: #fff; border: 1px solid #e2e8f0; color: #475569;
  cursor: pointer; transition: all .15s ease;
}
.sol-icon-btn:hover:not(:disabled) { border-color: #bfdbfe; color: var(--rf-primary); background: #eff6ff; }
.sol-icon-btn:disabled { opacity: .45; cursor: not-allowed; }
.sol-icon-btn-primary {
  background: var(--rf-primary); border-color: var(--rf-primary); color: #fff;
}
.sol-icon-btn-primary:hover:not(:disabled) { background: #1d56b4; border-color: #1d56b4; color: #fff; }
.dark .sol-icon-btn { background: #1e293b; border-color: #334155; color: #94a3b8; }
.dark .sol-icon-btn-primary { background: var(--rf-primary); border-color: var(--rf-primary); color: #fff; }

/* ── Filter bar ── */
.sol-filter-bar {
  display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
  padding: .45rem .65rem;
  border-radius: 13px;
  background: rgba(255,255,255,.94);
  border: 1px solid #dce5f0;
  box-shadow: 0 3px 12px rgba(24, 54, 94, .06);
  position: relative;
  /* Stacking context: el panel de filtros queda siempre sobre el thead sticky */
  z-index: 30;
  transition: box-shadow .2s ease, border-color .2s ease;
}
.sol-filter-bar:hover {
  border-color: #cbd8e8;
  box-shadow: 0 5px 16px rgba(24, 54, 94, .09);
}
.dark .sol-filter-bar { background: #171f2d; border-color: #2d3a4e; }
.sol-filter-divider {
  width: 1px; height: 24px;
  background: linear-gradient(180deg, transparent, #b8c8e0, transparent);
  flex-shrink: 0;
}

/* ── Tabs ── */
.sol-tabs {
  display: flex; gap: 2px;
  padding: 3px;
  background: #f4f7fb;
  border: 1px solid #e4eaf2;
  border-radius: 9px;
}
.dark .sol-tabs { background: #111827; border-color: #2d3a4e; }
.sol-tab {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 11px;
  border-radius: 7px;
  font-size: 12px; font-weight: 600; color: #64748b;
  background: transparent;
  border: none; cursor: pointer;
  transition: all .2s ease;
}
.sol-tab:hover { color: #1e2d55; background: rgba(13,45,107,.06); transform: translateY(-1px); }
.sol-tab-active {
  background: #174582;
  color: #fff;
  box-shadow: 0 2px 7px rgba(13,45,107,.2);
}
.sol-tab-count {
  padding: 1px 6px; border-radius: 999px;
  font-size: 9px; font-weight: 700;
  background: rgba(13,45,107,.08); color: #1e2d55;
}
.sol-tab-active .sol-tab-count { background: rgba(255,255,255,.2); color: #fff; }

/* Botón Filtros */
.sol-filtros-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 30px; padding: 0 12px;
  border-radius: 9px; border: 1px solid #d4deea;
  background: rgba(255,255,255,0.7);
  font-size: 11.5px; font-weight: 700; color: #475569;
  cursor: pointer; transition: all .15s ease;
  font-family: inherit;
}
.sol-filtros-btn:hover, .sol-filtros-btn-active {
  border-color: #16468E; color: #16468E; background: #eef4ff;
}
.dark .sol-filtros-btn { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #cbd5e1; }

/* ── Panel desplegable de filtros (abre el botón "Filtros") ── */
.sol-filtros-wrap { position: relative; }
.sol-tabs { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin: -2px 0 6px; }
.sol-tab {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 12px; border: 1px solid #dbe5f5; border-radius: 999px;
  background: rgba(255,255,255,.75); color: #3f5d84;
  font-size: 11px; font-weight: 700; cursor: pointer;
  transition: all .15s ease; font-family: inherit;
}
.sol-tab:hover { border-color: #93b9f5; background: #f0f6ff; color: #16468E; }
.sol-tab-active { border-color: #16468E; background: #16468E; color: #fff; box-shadow: 0 4px 12px rgba(22,70,142,.28); }
.sol-tab-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.sol-tab-pendiente .sol-tab-dot { background: #d9a028; }
.sol-tab-pendiente-cama .sol-tab-dot { background: #f07d22; }
.sol-tab-en-espera .sol-tab-dot { background: #4d8ef7; }
.sol-tab-active .sol-tab-dot { background: #fff; }
.sol-tab-count { padding: 1px 7px; border-radius: 999px; background: rgba(22,70,142,.1); font-size: 9.5px; font-weight: 800; }
.sol-tab-active .sol-tab-count { background: rgba(255,255,255,.22); }
.dark .sol-tab { border-color: #314666; background: #1c2d46; color: #bcd1ef; }
.dark .sol-tab:hover { border-color: #4d8ef7; color: #e2ecfc; }
.dark .sol-tab-active { border-color: #4d8ef7; background: #1f3d69; color: #e2ecfc; }
.dark .sol-tab-count { background: rgba(255,255,255,.1); }
.dark .sol-tab-active .sol-tab-count { background: rgba(255,255,255,.16); }
.sol-filtros-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 15px; height: 15px; padding: 0 4px;
  border-radius: 999px;
  background: #16468E; color: #fff;
  font-size: 8.5px; font-weight: 800;
}
.sol-filtros-btn-active .sol-filtros-badge { background: #fff; color: #16468E; }
.sol-filtros-panel {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 280px;
  max-width: calc(100vw - 32px);
  display: flex; flex-direction: column; gap: 8px;
  padding: 10px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 14px 36px rgba(15, 23, 42, 0.14);
  z-index: 60;
  max-height: min(420px, calc(100vh - 240px));
  overflow-y: auto;
}
.dark .sol-filtros-panel { background: #161b28; border-color: rgba(255,255,255,0.08); }
.sol-filtros-panel .sol-select { width: 100%; }
.sol-filtros-panel .sol-search { min-width: 0; max-width: none; width: 100%; }
.sol-filtros-panel .drf { width: 100%; }
.sol-filtros-panel :deep(.drf-trigger) { width: 100%; justify-content: flex-start; }
.sol-filtros-panel :deep(.el-select__wrapper),
.sol-filtros-panel :deep(.el-input__wrapper) {
  border-radius: 9px !important;
  min-height: 30px;
}
.sol-limpiar {
  font-size: 11.5px; font-weight: 700; color: #dc2626;
  background: none; border: none; cursor: pointer;
  font-family: inherit; padding: 4px 8px; border-radius: 7px;
  transition: background .15s ease;
}
.sol-limpiar:hover { background: #fef2f2; }

/* ── Search ── */
.sol-search { flex: 1; min-width: 200px; max-width: 400px; }
.sol-filter-bar :deep(.el-input__wrapper) {
  background: rgba(255,255,255,0.75) !important;
  border: 1px solid #d4deea !important;
  border-radius: 10px !important;
  transition: all .2s ease;
}
.sol-filter-bar :deep(.el-input__wrapper:hover) {
  border-color: #16468E !important;
  box-shadow: 0 0 0 2px rgba(22,70,142,0.08) !important;
}

/* ── Referencias en tarjetas ── */
.sol-list-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; position: relative; z-index: 30; padding: 3px 0 5px; }
.sol-list-heading { display: flex; align-items: baseline; gap: 13px; flex: 1; min-width: 250px; }
.sol-list-heading h1 { margin: 0; color: #102451; font-size: 25px; font-weight: 750; letter-spacing: -.65px; line-height: 1.3; }
.sol-list-heading > span { color: #6b84ae; font-size: 13px; white-space: nowrap; }
.sol-list-search { width: 250px; flex: 0 1 250px; }
.sol-list-search :deep(.el-input__wrapper) { min-height: 30px; border-radius: 7px; box-shadow: 0 0 0 1px #dbe6f5 inset; background: rgba(255,255,255,.8); }
.sol-list-search :deep(.el-input__inner) { color: #244575; font-size: 12px; }
.sol-list-tools { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.sol-list-tools button { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 30px; padding: 6px 9px; border: 1px solid #dbe6f5; border-radius: 7px; background: rgba(255,255,255,.8); color: #315583; font-size: 11px; white-space: nowrap; cursor: pointer; transition: background .15s ease, border-color .15s ease; }
.sol-list-tools button:hover:not(:disabled) { background: #e4efff; }
.sol-list-tools button:disabled { opacity: .5; cursor: not-allowed; }
.sol-body { flex: 0 1 auto; }
.sol-list-panel { display: flex; flex-direction: column; container-type: inline-size; }
.sol-reference-scroll { flex: 0 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 2px 3px 3px 1px; }
.sol-reference-list { display: flex; flex-direction: column; gap: 10px; }
.sol-reference-card {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, .8fr) minmax(0, 1.2fr) minmax(185px, .67fr) 100px;
  grid-template-areas: "patient clinic clinical state actions";
  align-items: center;
  gap: 14px;
  min-height: 86px;
  padding: 11px 14px;
  border: 1px solid rgba(225,235,248,.7);
  border-radius: 14px;
  background: linear-gradient(115deg, #fff, #fbfdff);
  box-shadow: 0 2px 8px rgba(42,88,149,.06);
  transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}
.sol-reference-card:hover { border-color: #a9c8f7; box-shadow: 0 8px 22px rgba(42,88,149,.14); transform: translateY(-1px); }
.sol-reference-card > div { min-width: 0; }
.sol-reference-urgent {
  border-color: #f43f5e;
  background: linear-gradient(110deg, #ffdfe7, #fff0f3);
  animation: sol-vital-card-glow 1.8s ease-in-out infinite;
}
@keyframes sol-vital-card-glow {
  0%, 100% { box-shadow: inset 4px 0 0 #dc1139, 0 0 0 0 rgba(225, 29, 72, .4); }
  50% { box-shadow: inset 4px 0 0 #be123c, 0 0 0 6px rgba(225, 29, 72, 0); }
}
.sol-reference-urgent:hover { border-color: #e11d48; }
.sol-reference-card--pendiente { border-color: #f2cf7d; background: linear-gradient(110deg, #fff4d8 0%, #fffaeb 60%, #fffdf5 100%); box-shadow: inset 4px 0 0 #f59e0b; }
.sol-reference-card--pendiente:hover { border-color: #eab308; box-shadow: inset 4px 0 0 #d97706, 0 8px 20px rgba(217,119,6,.16); }
.sol-reference-card--pendiente_cama { border-color: #f5bd82; background: linear-gradient(110deg, #ffecd6 0%, #fff6ea 60%, #fffcf5 100%); box-shadow: inset 4px 0 0 #f97316; }
.sol-reference-card--pendiente_cama:hover { border-color: #f09b4a; box-shadow: inset 4px 0 0 #ea580c, 0 8px 20px rgba(234,88,12,.16); }
.sol-reference-card--en_espera { border-color: #9ec9f9; background: linear-gradient(110deg, #dcecfe 0%, #f0f8ff 60%, #f8fcff 100%); box-shadow: inset 4px 0 0 #3b82f6; }
.sol-reference-card--en_espera:hover { border-color: #60a5fa; box-shadow: inset 4px 0 0 #2563eb, 0 8px 20px rgba(37,99,235,.16); }
.sol-reference-card--completado { border-color: #a4dcb4; background: linear-gradient(110deg, #e2f7e9 0%, #f2fcf5 60%, #f9fdfb 100%); box-shadow: inset 4px 0 0 #22c55e; }
.sol-reference-card--negado { border-color: #f0a3ad; background: linear-gradient(110deg, #ffe4ea 0%, #fff3f5 60%, #fffbfc 100%); box-shadow: inset 4px 0 0 #ef4444; }
.sol-reference-highlighted { outline: 2px solid #60a5fa; outline-offset: -2px; }
.sol-reference-patient { grid-area: patient; display: flex; align-items: flex-start; gap: 10px; }
.sol-reference-avatar {
  display: grid; place-items: center; width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
  font-size: 13px; font-weight: 750;
  box-shadow: 0 0 0 2px #fff, 0 4px 10px rgba(20,40,80,.16);
  text-shadow: 0 1px 1px rgba(255,255,255,.35);
}
.sol-reference-identity { min-width: 0; flex: 1; }
.sol-reference-name { display: block; max-width: 100%; padding: 0; border: 0; background: none; color: #102451; font: inherit; font-size: 14px; font-weight: 750; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sol-reference-name:hover { color: #2563eb; }
.sol-reference-name:focus-visible { outline: 2px solid #60a5fa; outline-offset: 2px; border-radius: 3px; }
.sol-reference-document { margin: 3px 0 4px; color: #758db5; font-size: 11px; white-space: nowrap; }
.sol-reference-insurer { display: flex; align-items: center; gap: 7px; min-width: 0; }
.sol-reference-insurer-label { display: inline-flex; align-items: center; gap: 4px; color: #486996; font-size: 9px; flex-shrink: 0; }
.sol-reference-insurer-label svg { width: 12px; height: 12px; color: #1d4ed8; }
.sol-reference-insurer-chip { padding: 3px 8px; border: 1px solid #cfe2ff; border-radius: 999px; background: #e9f2ff; color: #1e4688; font-size: 9.5px; font-weight: 600; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sol-reference-clinic { grid-area: clinic; }
.sol-reference-clinical { grid-area: clinical; }
.sol-reference-state { grid-area: state; }
.sol-reference-divider { padding-left: 17px; border-left: 1px solid #dfe8f7; }
.sol-reference-label { display: block; color: #6c87b0; font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; line-height: 1.3; }
.sol-reference-clinic-name { margin: 3px 0; color: #18345e; font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sol-reference-contact { margin: 0; color: #728bb3; font-size: 10.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sol-reference-specialty { margin: 3px 0 0; color: #075ce8; font-size: 13px; font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sol-reference-diagnosis-label { margin-top: 5px; }
.sol-reference-diagnosis { margin: 2px 0 0; color: #34517a; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sol-reference-status { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; padding: 4px 8px; border: 1px solid transparent; border-radius: 999px; font-size: 11px; font-weight: 600; line-height: 1.15; white-space: nowrap; }
.sol-reference-status .sol-status-dot { background: currentColor; flex-shrink: 0; }
.sol-reference-status--pendiente { background: #fff3cc; color: #b45309; border-color: #fde68a; }
.sol-reference-status--pendiente_cama { background: #ffecd9; color: #e45a00; border-color: #fed7aa; }
.sol-reference-status--en_espera { background: #e1edff; color: #0960e8; border-color: #bfdbfe; }
.sol-reference-status--completado { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
/* Origen de la solicitud: se muestra como un dato más dentro de la
   tarjeta (Correo electrónico vs Aplicativo) — visible pero sin píldora. */
.sol-reference-avatar { position: relative; }
.sol-correo-badge {
  position: absolute; right: -4px; bottom: -4px;
  display: grid; place-items: center;
  width: 18px; height: 18px; border-radius: 50%;
  color: #fff;
  background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
  box-shadow: 0 0 0 2px #fff, 0 2px 5px rgba(37, 99, 235, .45);
}
.sol-correo-badge svg { width: 9px; height: 9px; }
.dark .sol-correo-badge { box-shadow: 0 0 0 2px #161b28, 0 2px 5px rgba(0,0,0,.5); }
.sol-origen { display: flex; flex-direction: column; gap: 2px; margin-bottom: 2px; }
.sol-origen-val {
  display: inline-flex; align-items: center; gap: 5px; max-width: 100%;
  font-size: 12.5px; font-weight: 800; letter-spacing: .01em;
  line-height: 1.2; white-space: nowrap;
  border-left: 3px solid; padding-left: 7px;
}
.sol-origen-val svg { width: 14px; height: 14px; flex-shrink: 0; }
.sol-origen--correo .sol-origen-val { color: #0369a1; border-color: #38bdf8; }
.sol-origen--app .sol-origen-val { color: #4f46e5; border-color: #a5b4fc; }
.dark .sol-origen--correo .sol-origen-val { color: #7dd3fc; border-color: #0ea5e9; }
.dark .sol-origen--app .sol-origen-val { color: #a5b4fc; border-color: #6366f1; }
.sol-reference-status--negado { background: #ffe0e7; color: #df123d; font-weight: 650; }
.sol-reference-vital {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: #fff;
  border-color: #b91c1c;
  font-weight: 800;
  text-shadow: 0 1px 2px rgba(0, 0, 0, .18);
}
.sol-reference-vital .sol-status-dot { background: #fff; }
.sol-reference-date { margin: 4px 0 3px; color: #506b95; font-size: 10.5px; white-space: nowrap; }
.sol-reference-age { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.sol-reference-age .sol-edad, .sol-reference-age .sol-reference-status { margin: 0; font-size: 9.5px; font-weight: 500; padding: 3px 8px; line-height: 1.15; }
.sol-reference-actions { grid-area: actions; gap: 5px; align-items: center; }
.sol-reference-actions :deep(.el-button) { width: 30px; height: 30px; border-color: #dbe5f5; background: #fff; color: #244671; }
.sol-reference-actions :deep(.el-button:hover) { color: #2563eb; border-color: #93b9f5; background: #f0f6ff; }

.dark .sol-list-heading h1 { color: #e5eeff; }
.dark .sol-list-heading > span, .dark .sol-reference-label { color: #90abd2; }
.dark .sol-list-search :deep(.el-input__wrapper) { background: #17243a; box-shadow: 0 0 0 1px #314666 inset; }
.dark .sol-list-search :deep(.el-input__inner) { color: #c7d9f5; }
.dark .sol-list-tools button { border-color: #314666; background: #1c2d46; color: #bcd1ef; }
.dark .sol-list-tools button:hover:not(:disabled) { background: #263d5d; }
.dark .sol-reference-card { background: linear-gradient(115deg, #192439, #162133); border-color: #293b56; box-shadow: 0 3px 10px rgba(0,0,0,.12); }
.dark .sol-reference-card:hover { border-color: #4771ab; transform: translateY(-1px); box-shadow: 0 8px 22px rgba(0,0,0,.3); }
.dark .sol-reference-avatar { box-shadow: 0 0 0 2px #161b28, 0 4px 10px rgba(0,0,0,.45); text-shadow: none; }
.dark .sol-reference-urgent { border-color: #e11d48; background: linear-gradient(110deg, #4a1f2e, #331c29); animation: sol-vital-card-glow 1.8s ease-in-out infinite; }
.dark .sol-reference-vital { background: linear-gradient(135deg, #e11d48, #be123c); color: #fff; border-color: #f43f5e; }
.dark .sol-reference-card--pendiente { border-color: #6b5526; background: linear-gradient(110deg, #33290f, #28220f); box-shadow: inset 4px 0 0 #d9a028; }
.dark .sol-reference-card--pendiente_cama { border-color: #7a4d21; background: linear-gradient(110deg, #35240f, #2a1f10); box-shadow: inset 4px 0 0 #f07d22; }
.dark .sol-reference-card--en_espera { border-color: #2e5a9c; background: linear-gradient(110deg, #16263f, #182135); box-shadow: inset 4px 0 0 #4d8ef7; }
.dark .sol-reference-card--completado { border-color: #2f6b44; background: linear-gradient(110deg, #14291c, #16241b); box-shadow: inset 4px 0 0 #34c468; }
.dark .sol-reference-card--negado { border-color: #7c3a44; background: linear-gradient(110deg, #331e24, #2a1d22); box-shadow: inset 4px 0 0 #f05656; }
.dark .sol-reference-divider { border-color: #324461; }
.dark .sol-reference-name, .dark .sol-reference-clinic-name { color: #e2ecfc; }
.dark .sol-reference-specialty { color: #75afff; }
.dark .sol-reference-document, .dark .sol-reference-contact, .dark .sol-reference-date, .dark .sol-reference-insurer-label { color: #9db4d6; }
.dark .sol-reference-diagnosis { color: #bfd0e8; }
.dark .sol-reference-insurer-chip { background: #223d64; color: #c7dfff; }
.dark .sol-reference-status--pendiente { background: #483817; color: #fcd566; }
.dark .sol-reference-status--pendiente_cama { background: #492e20; color: #ffb879; }
.dark .sol-reference-status--en_espera { background: #1f3d69; color: #9ec8ff; }
.dark .sol-reference-status--completado { background: #184333; color: #86efac; }
.dark .sol-reference-status--negado { background: #592539; color: #ffa7bb; }
.dark .sol-reference-actions :deep(.el-button) { background: #1b2b44; border-color: #3b5273; color: #bcd5f7; }

@container (max-width: 850px) {
  .sol-reference-card { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) 100px; grid-template-areas: "patient clinic actions" "clinical state state"; gap: 12px; }
  .sol-reference-clinical { border-left: 0; padding-left: 45px; }
  .sol-reference-state { padding-top: 4px; }
  .sol-reference-name { white-space: normal; }
}
@container (max-width: 520px) {
  .sol-reference-card { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "patient actions" "clinic clinic" "clinical clinical" "state state"; align-items: start; gap: 8px; padding: 14px; border-radius: 14px; box-shadow: 0 6px 16px rgba(42,88,149,.09); }
  .sol-reference-patient { align-items: flex-start; }
  .sol-reference-name { font-size: 15px; font-weight: 800; letter-spacing: -.01em; white-space: normal; }
  .sol-reference-avatar { width: 40px; height: 40px; font-size: 13px; }
  .sol-reference-actions { align-self: start; gap: 6px; }
  .sol-reference-actions :deep(.el-button) { width: 32px; height: 32px; }
  .sol-reference-divider { padding: 8px 10px; border: 1px solid rgba(255,255,255,.85); border-left: 3px solid #93b9f5; border-radius: 10px; background: rgba(255,255,255,.62); }
  .sol-reference-clinical.sol-reference-divider { border-left-color: #7c8cf0; }
  .sol-reference-label { font-size: 9px; font-weight: 700; letter-spacing: .05em; }
  .sol-reference-clinic-name, .sol-reference-contact, .sol-reference-specialty, .sol-reference-diagnosis { white-space: normal; overflow-wrap: anywhere; }
  .sol-reference-clinic-name { font-size: 13.5px; }
  .sol-reference-specialty { font-size: 13px; font-weight: 750; }
  .sol-reference-document { white-space: normal; }
  .sol-reference-insurer-label { display: none; }
  .sol-reference-state.sol-reference-divider { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 8px; padding: 8px 0 0; border: 0; border-top: 1px dashed rgba(100,130,180,.35); border-radius: 0; background: transparent; }
  .sol-reference-date { width: 100%; margin: 0; order: 2; font-size: 11px; }
  .dark .sol-reference-divider { border-color: rgba(255,255,255,.08); border-left-color: #4d8ef7; background: rgba(255,255,255,.045); }
  .dark .sol-reference-clinical.sol-reference-divider { border-left-color: #8b9bff; }
  .dark .sol-reference-state.sol-reference-divider { border-color: transparent; border-top: 1px dashed rgba(255,255,255,.16); background: transparent; }
}
@media (max-width: 700px) {
  .sol-list-heading { flex-basis: 100%; }
  .sol-list-heading h1 { font-size: 23px; }
  .sol-list-search { flex: 1; width: auto; }
  .sol-list-tools { order: 1; flex-basis: 100%; }
  .sol-list-tools button { flex: 1; }
  .sol-pagination-info { flex-basis: 100%; }
}

/* ── Table panel ── */
.sol-table-panel {
  background: #fff;
  border: 1px solid #dce5f0;
  border-radius: 14px;
  box-shadow: 0 5px 18px rgba(24,54,94,.07);
  padding: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.dark .sol-table-panel { background: #161b28; border-color: rgba(255,255,255,0.07); }

/* ── Table loading ── */
.sol-table-loading {
  display: flex; flex-direction: column; gap: 6px;
  padding: 4px;
}
.sol-table-row-skeleton {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border-radius: 8px;
  background: rgba(255,255,255,0.8);
  border: 1px solid #e2e8f0;
}

/* ── Table ── */
.sol-table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
.sol-th {
  padding: 10px 9px;
  text-align: left;
  font-size: 10px; font-weight: 750;
  text-transform: uppercase; letter-spacing: .055em;
  color: rgba(255,255,255,.92);
  background: #173f75;
  border-bottom: 1px solid #12345f;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sol-th:first-child { border-radius: 8px 0 0 8px; }
.sol-th:last-child { border-radius: 0 8px 8px 0; }
.sol-table-thead th { position: sticky; top: 0; z-index: 10; }
.dark .sol-table-thead .sol-th { background: #1b2c43; border-bottom-color: #2a3b54; }
.sol-td {
  padding: 10px 9px;
  font-size: 12.5px; color: #334155;
  border-bottom: 1px solid #edf1f6;
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.dark .sol-td { color: #cbd5e1; border-bottom-color: #293548; }
.sol-table-row { transition: background .15s ease, box-shadow .15s ease; }
/* Acento lateral por estado — ayuda a leer la cola de un vistazo */
.sol-row-pendiente { box-shadow: inset 3px 0 0 #fbbf24; }
.sol-row-pendiente_cama { box-shadow: inset 3px 0 0 #f97316; }
.sol-row-en_espera { box-shadow: inset 3px 0 0 #3b82f6; }
.sol-row-completado { box-shadow: inset 3px 0 0 #22c55e; }
.sol-row-negado { box-shadow: inset 3px 0 0 #ef4444; }
.sol-table-row:nth-child(even) { background: #fbfcfe; }
.sol-table-row:hover { background: #f1f6fd; box-shadow: inset 3px 0 0 var(--rf-primary); }
.dark .sol-table-row:nth-child(even) { background: #182232; }
.dark .sol-table-row:hover { background: #1e2b3d; }
.sol-table-row.sol-row-urgencia-vital {
  background: #fff1f2;
  box-shadow: inset 4px 0 0 #dc2626;
}
.sol-table-row.sol-row-urgencia-vital:hover {
  background: #ffe4e6;
  box-shadow: inset 4px 0 0 #b91c1c;
}
.dark .sol-table-row.sol-row-urgencia-vital { background: rgba(127, 29, 29, .18); }
.dark .sol-table-row.sol-row-urgencia-vital:hover { background: rgba(127, 29, 29, .3); }
.sol-table-row:hover .sol-table-status { transform: scale(1.06); }
.sol-table-row:last-child td { border-bottom: none; }
.sol-th-actions { text-align: right; }

.sol-table-paciente { display: flex; align-items: center; gap: 10px; }
.sol-table-paciente-info { min-width: 0; }
.sol-table-avatar {
  width: 34px; height: 34px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 11px; font-weight: 800;
}
.sol-table-name {
  font-size: 13px; font-weight: 750; color: #172b49;
  margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .sol-table-name { color: #e2e8f0; }
.sol-table-clinica-name {
  font-size: 12.5px; font-weight: 700; color: #1e4b83; margin: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .sol-table-clinica-name { color: #93c5fd; }
.sol-table-remitente {
  font-size: 10px; color: #94a3b8; margin: 2px 0 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sol-table-doc { font-size: 10px; color: #94a3b8; margin: 1px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sol-eps-chip {
  display: inline-block; max-width: 100%;
  padding: 3px 9px; border-radius: 999px;
  font-size: 10.5px; font-weight: 700; letter-spacing: .01em;
  color: #0d2d6b; background: #eef4ff; border: 1px solid #d8e4fa;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  vertical-align: middle;
}
.dark .sol-eps-chip {
  color: #93c5fd; background: rgba(59,130,246,.12); border-color: rgba(96,165,250,.25);
}
.sol-table-status {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 999px;
  font-size: 10px; font-weight: 700; white-space: nowrap;
  transition: transform .2s ease;
}
.sol-status-stack { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.sol-urgencia-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: 100%;
  padding: 3px 7px;
  border: 1px solid #fecaca;
  border-radius: 999px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: .025em;
  line-height: 1;
  white-space: nowrap;
}
.sol-urgencia-badge-dot {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
  border-radius: 50%;
  background: #dc2626;
  box-shadow: 0 0 0 2px rgba(220, 38, 38, .12);
}
.dark .sol-urgencia-badge { background: rgba(127, 29, 29, .35); border-color: rgba(248, 113, 113, .4); color: #fca5a5; }
.dark .sol-urgencia-badge-dot { background: #f87171; }
.sol-status-dot { width: 5px; height: 5px; border-radius: 50%; }
/* Urgencia vital — punto parpadeante + anillo pulsante en el chip */
@keyframes sol-vital-dot-pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.45); opacity: .55; }
}
@keyframes sol-vital-chip-ring {
  0%, 100% { box-shadow: 0 0 0 0 rgba(223, 18, 61, .35); }
  50% { box-shadow: 0 0 0 4px rgba(223, 18, 61, 0); }
}
.sol-reference-vital,
.sol-urgencia-badge,
.sol-urgencia-detail-badge { animation: sol-vital-chip-ring 1.5s ease-in-out infinite; }
.sol-reference-vital .sol-status-dot,
.sol-urgencia-badge-dot { animation: sol-vital-dot-pulse 1s ease-in-out infinite; }
.sol-status-pending { background: #fef3c7; color: #d97706; }
.sol-status-pending .sol-status-dot { background: #fbbf24; }
.sol-status-cama { background: #ffedd5; color: #ea580c; }
.sol-status-cama .sol-status-dot { background: #f97316; }
.sol-status-waiting { background: #dbeafe; color: #1d4ed8; }
.sol-status-waiting .sol-status-dot { background: #3b82f6; }
.sol-status-completed { background: #dcfce7; color: #16a34a; }
.sol-status-completed .sol-status-dot { background: #22c55e; }
.sol-status-rejected { background: #fee2e2; color: #dc2626; }
.sol-status-rejected .sol-status-dot { background: #ef4444; }
.sol-table-actions { display: flex; justify-content: flex-end; gap: 6px; }
.sol-table-actions .el-button {
  margin-left: 0 !important;
  transition: transform .2s cubic-bezier(.22,1,.36,1), box-shadow .2s ease, filter .2s ease;
}
.sol-evo-wrap { position: relative; display: inline-flex; }
.sol-evo-count {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 15px;
  height: 15px;
  padding: 0 4px;
  border-radius: 999px;
  background: #1d4ed8;
  color: #fff;
  font-size: 8px;
  font-weight: 800;
  display: grid;
  place-items: center;
  border: 1.5px solid #fff;
  pointer-events: none;
  z-index: 1;
}
.dark .sol-evo-count { border-color: #131a2a; }
.sol-table-actions .sol-evoluciones-btn {
  color: #2563eb;
  border-color: #bfdbfe;
  background: #eff6ff;
}
.sol-table-actions .sol-evoluciones-btn:hover {
  color: #fff;
  border-color: #2563eb;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  box-shadow: 0 5px 13px rgba(37,99,235,.25);
  transform: translateY(-1px);
}
.dark .sol-table-actions .sol-evoluciones-btn {
  color: #93c5fd;
  border-color: #31527c;
  background: #172b47;
}
.dark .sol-table-actions .sol-evoluciones-btn:hover { color: #fff; background: #2563eb; }
.sol-table-actions .el-button:hover {
  filter: brightness(1.08);
}
.sol-table-actions .el-button:active {
  filter: brightness(0.95);
}
.sol-table-actions .el-button--success:hover {
  box-shadow: 0 6px 16px rgba(22,163,74,.30);
}
.sol-table-actions .el-button--primary:hover {
  box-shadow: 0 6px 16px rgba(37,99,235,.30);
}
.sol-table-actions .el-button--danger:hover {
  box-shadow: 0 6px 16px rgba(220,38,38,.30);
}
.sol-table-actions .el-button--default:hover {
  box-shadow: 0 6px 16px rgba(13,45,107,.15);
}

/* ── Empty state ── */
.sol-empty-wrap {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  text-align: center; padding: 2rem 1rem;
  height: 100%;
}
.sol-empty-icon {
  color: #2f70bb;
  background: linear-gradient(135deg, #e4f0ff, #dbeafe);
  box-shadow: 0 8px 20px rgba(47, 112, 187, .15);
}

/* ── Scrollbar ── */
/* El estilo del scroll es global en tailwind.css (invisible hasta hover, tipo Element Plus) */

/* ── Animaciones ── */
.anim-row-in { animation: rowIn 0.4s cubic-bezier(.22,1,.36,1) both; }
@keyframes rowIn { from { opacity:0; transform:translateX(-12px); } to { opacity:1; transform:none; } }

/* ── Shimmer ── */
.shimmer-box, .shimmer-bar { position: relative; overflow: hidden; background: #e6ebf3; }
.shimmer-box { border-radius: 6px; }
.shimmer-bar { border-radius: 4px; }
.shimmer-box::after, .shimmer-bar::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.6) 50%, transparent 100%);
  animation: shimmer 1.8s ease-in-out infinite;
}
@keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

/* ── Modal Detalle ── */
:deep(.detalle-dialog) {
  width: min(1140px, 95vw) !important;
  max-height: calc(100vh - 2rem);
  display: flex;
  flex-direction: column;
  border-radius: 22px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(11, 35, 73, .4), 0 0 0 1px rgba(255,255,255,.08);
}
:deep(.detalle-dialog .el-dialog__header) { display: none; }
:deep(.detalle-dialog .el-dialog__body) {
  padding: 0;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

:deep(.el-overlay) { background-color: rgba(8, 27, 58, .56); backdrop-filter: blur(4px); }

.detalle-content {
  padding: 0;
  position: relative;
  background: #F8FAFC;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

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
  gap: .55rem;
  padding: .5rem 3.5rem .5rem 1rem;
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
  width: 32px; height: 32px;
  border-radius: 10px;
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
.detalle-head-badge.estado-pendiente_cama { background: #ffedd5; color: #c2410c; }
.detalle-head-badge.estado-en_espera { background: #dbeafe; color: #1d4ed8; }
.detalle-head-badge.estado-completado { background: #dcfce7; color: #16a34a; }
.detalle-head-badge.estado-negado { background: #fee2e2; color: #b91c1c; }
.detalle-head-badge.estado-cancelado { background: #e2e8f0; color: #475569; }

.sol-urgencia-detail-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .3rem .65rem;
  border: 1px solid #fecaca;
  border-radius: 999px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: .62rem;
  font-weight: 800;
  white-space: nowrap;
}

.detalle-head-pdf {
  position: relative; z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .4rem .75rem;
  border-radius: 9px;
  background: #fff;
  border: 1px solid #dce7f2;
  color: #16468e;
  font-size: .68rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
  flex-shrink: 0;
}
.detalle-head-pdf:hover { background: #eff6ff; border-color: #86b4e8; }

.detalle-body {
  padding: .5rem .9rem;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

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
.detalle-card:hover {
  box-shadow: 0 10px 28px rgba(22,70,142,.14);
  border-color: #b8c8de;
  transform: translateY(-1px);
}
.detalle-card-head {
  display: flex;
  align-items: center;
  gap: .35rem;
  padding: .35rem .55rem .15rem;
}
.detalle-card-icon {
  width: 1.45rem; height: 1.45rem;
  border-radius: 7px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.detalle-card-icon-blue { background: #dbeafe; color: #2563eb; }
.detalle-card-icon-amber { background: #fef3c7; color: #d97706; }
.detalle-card-icon-violet { background: #ede9fe; color: #7c3aed; }
.detalle-card-icon-slate { background: #f1f5f9; color: #475569; }
.detalle-card-icon-rose { background: #fee2e2; color: #dc2626; }
.detalle-card-title {
  margin: 0;
  font-size: .74rem;
  font-weight: 800;
}
.detalle-card-title-blue { color: #1e40af; }
.detalle-card-title-amber { color: #b45309; }
.detalle-card-title-violet { color: #6d28d9; }
.detalle-card-title-slate { color: #334155; }
.detalle-card-title-rose { color: #b91c1c; }

.card-body { padding: 0 .55rem .35rem; }
.card-text { font-size: .72rem; color: #334e70; line-height: 1.5; margin: 0; }
.card-text-sm { font-size: .66rem; color: #64748b; line-height: 1.45; margin: 0; }
.detalle-layout {
  display: grid;
  grid-template-columns: 1.9fr 1fr;
  grid-template-rows: minmax(0, 1fr);
  gap: 6px;
  flex: 1;
  min-height: 0;
  margin-bottom: 6px;
}
.detalle-layout-side {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  min-height: 0;
  overflow-y: auto;
  scrollbar-width: thin;
}
.detalle-layout-side > .detalle-card { flex-shrink: 0; }
.detalle-hc-card { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.detalle-hc-body { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.detalle-hc-box {
  flex: 1;
  min-height: 120px;
  overflow-y: auto;
  scrollbar-width: thin;
  padding: .5rem .6rem;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #e8eef5;
}
@media (max-width: 700px) {
  .detalle-body { overflow-y: auto; }
  .detalle-layout { grid-template-columns: 1fr; grid-template-rows: none; }
  .detalle-hc-box { min-height: 110px; max-height: 220px; }
  .detalle-layout-side { overflow-y: visible; }
}
.detalle-hc-box::-webkit-scrollbar { width: 5px; }
.detalle-hc-box::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.detalle-hc-box::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.dark .detalle-hc-box { background: rgba(15, 23, 42, .4); border-color: #243247; }

.card-dx-list { display: flex; flex-direction: column; gap: 6px; }
.card-dx-item {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 8px 10px; border-radius: 10px;
  background: #f5f3ff;
  border: 1px solid #e9e5fb;
}
.card-dx-code {
  font-size: 10px; font-weight: 800; color: #7c3aed;
  font-family: monospace; flex-shrink: 0;
  background: #ede9fe; border-radius: 6px;
  padding: 1px 6px; margin-top: 1px;
}
.card-dx-desc { font-size: 12px; color: #334155; line-height: 1.4; }
.dark .card-dx-item { background: rgba(124, 58, 237, .1); border-color: rgba(124, 58, 237, .25); }
.dark .card-dx-code { background: rgba(124, 58, 237, .22); color: #c4b5fd; }
.dark .card-dx-desc { color: #cbd5e1; }

.sol-td-fecha { white-space: nowrap; }
.sol-fecha { margin: 0; font-size: 11px; font-weight: 600; color: #334155; }
.dark .sol-fecha { color: #cbd5e1; }
.sol-hora { margin: 0; font-size: 10px; color: #94a3b8; }
.sol-edad {
  display: inline-block; margin-top: 3px; padding: 1px 7px; border-radius: 999px;
  font-size: 9px; font-weight: 800; letter-spacing: .02em; white-space: nowrap;
}
.sol-edad--ok { color: #1d4ed8; background: #dbeafe; }
.sol-edad--warn { color: #b45309; background: #fef3c7; }
.sol-edad--alta { color: #c2410c; background: #ffedd5; }
.sol-edad--crit { color: #b91c1c; background: #fee2e2; }
.dark .sol-edad--ok { color: #93c5fd; background: rgba(59,130,246,.15); }
.dark .sol-edad--warn { color: #fbbf24; background: rgba(245,158,11,.12); }
.dark .sol-edad--alta { color: #fb923c; background: rgba(249,115,22,.12); }
.dark .sol-edad--crit { color: #f87171; background: rgba(239,68,68,.12); }

.data-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: .4rem;
  padding: .08rem 0;
  border-bottom: 1px solid #f1f5f9;
}
.data-row:last-child { border-bottom: none; }
.data-row span { font-size: .62rem; color: #94a3b8; white-space: nowrap; }
.data-row strong { font-size: .68rem; color: #1e293b; font-weight: 600; text-align: right; }

/* Timeline (Seguimiento) */
.timeline-item {
  display: flex;
  gap: .5rem;
  align-items: flex-start;
}
.timeline-connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}
.timeline-dot {
  width: 17px; height: 17px;
  border-radius: 50%;
  display: grid; place-items: center;
  background: #f1f5f9;
  color: #94a3b8;
  border: 2px solid #e2e8f0;
  transition: all .2s ease;
}
.timeline-dot-done {
  background: #dbeafe;
  color: #2563eb;
  border-color: #bfdbfe;
}
.timeline-dot-negada {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fecaca;
}
.timeline-dot-revertido {
  background: #fef3c7;
  color: #b45309;
  border-color: #fde68a;
}
.timeline-line {
  width: 2px;
  flex: 1;
  min-height: 10px;
  background: #e2e8f0;
  margin: 1px 0;
}
.timeline-line-done { background: #bfdbfe; }
.timeline-text { padding-bottom: .4rem; padding-top: 0; min-width: 0; }
.timeline-titulo { font-size: .68rem; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.2; }
.timeline-titulo-pending { color: #94a3b8; }
.timeline-fecha { font-size: .58rem; color: #94a3b8; margin: .05rem 0 0; }
.timeline-obs {
  font-size: .62rem; color: #475569; line-height: 1.4;
  margin: .2rem 0 0 1.55rem;
  background: #f8fafc; border-radius: 6px; padding: .3rem .5rem;
}
.timeline-obs-negada { background: #fef2f2; color: #b91c1c; }

/* Adjuntos (Soportes) */
.adjunto-list { display: flex; flex-direction: column; gap: .3rem; }
.adjunto-row {
  display: flex;
  align-items: center;
  gap: .45rem;
  padding: .25rem;
  border-radius: 10px;
  transition: background .15s ease;
  width: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
}
.adjunto-row:hover { background: #f8fafc; }
.adjunto-thumb {
  width: 28px; height: 28px;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
}
.adjunto-icon {
  width: 28px; height: 28px;
  display: grid; place-items: center;
  font-size: .5rem;
  font-weight: 800;
  color: #fff;
  border-radius: 6px;
  flex-shrink: 0;
}
.adjunto-pdf { background: #dc2626; }
.adjunto-img { background: #2563eb; }
.adjunto-info { min-width: 0; flex: 1; }
.adjunto-name {
  font-size: .68rem;
  color: #334e70;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.adjunto-meta { font-size: .58rem; color: #94a3b8; margin: .05rem 0 0; }
.adjunto-action-icon { color: #94a3b8; flex-shrink: 0; }
.adjunto-row:hover .adjunto-action-icon { color: #16468e; }
.adjunto-empty {
  font-size: .66rem;
  color: #cbd5e1;
  font-style: italic;
  margin: 0;
  padding: .3rem 0;
}

/* Footer */
.detalle-footer {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .6rem 1.1rem;
  background: #fff;
  border-top: 1px solid #F1F5F9;
}
.ingreso-footer-group { margin-left: auto; display: flex; align-items: center; gap: .5rem; }
.ingreso-num-input { width: 140px; }
.detalle-footer-note {
  flex: 1;
  display: flex;
  align-items: center;
  gap: .35rem;
  margin: 0;
  font-size: .66rem;
  font-weight: 500;
  color: #94A3B8;
}
.detalle-cerrar-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .55rem 1.1rem;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #16468e, #2563eb);
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(37, 99, 235, .3);
  transition: all .2s ease;
  flex-shrink: 0;
}
.detalle-cerrar-btn:hover {
  background: linear-gradient(135deg, #1d55ad, #3b82f6);
  box-shadow: 0 6px 20px rgba(37, 99, 235, .4);
}

/* ── Modales de acción (Aceptar/Negar/En espera/Completar) ── */
:deep(.accion-dialog) {
  border-radius: 22px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(11, 35, 73, .4), 0 0 0 1px rgba(255,255,255,.08);
}
:deep(.accion-dialog .el-dialog__header) { display: none; }
:deep(.accion-dialog .el-dialog__body) { padding: 0; overflow: hidden; }

.accion-icon-success { background: linear-gradient(135deg, #22c55e, #16a34a); box-shadow: 0 6px 16px rgba(22,163,74,.3); }
.accion-icon-danger { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 6px 16px rgba(220,38,38,.3); }
.accion-icon-info { background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 6px 16px rgba(37,99,235,.3); }
.accion-icon-violet { background: linear-gradient(135deg, #818cf8, #6366f1); box-shadow: 0 6px 16px rgba(99,102,241,.3); }
.accion-icon-amber { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 6px 16px rgba(217,119,6,.3); }
.accion-icon-teal { background: linear-gradient(135deg, #2dd4bf, #0d9488); box-shadow: 0 6px 16px rgba(13,148,136,.3); }

.accion-patient-card {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .5rem .75rem;
  border-radius: 12px;
  background: linear-gradient(120deg, #0D2D6B 0%, #16468E 100%);
  box-shadow: 0 6px 16px rgba(13,45,107,.22);
  margin-bottom: .45rem;
}
.accion-patient-avatar {
  display: grid;
  place-items: center;
  width: 34px; height: 34px;
  flex-shrink: 0;
  border-radius: 50%;
  background: rgba(255,255,255,.18);
  color: #fff;
  font-size: .68rem;
  font-weight: 800;
}
.accion-patient-info { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
.accion-patient-info strong { font-size: .82rem; font-weight: 800; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.accion-patient-info span { font-size: .66rem; color: rgba(255,255,255,.75); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.accion-form-body { padding: .55rem .7rem .5rem; }
.accion-field-label {
  display: block;
  font-size: .66rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: .3rem;
}
.accion-required { color: #dc2626; }
.accion-field-hint { font-size: .62rem; color: #94a3b8; margin: .25rem 0 0; line-height: 1.4; }
.correo-destino-row { display: flex; align-items: center; gap: 8px; }
.correo-destino-chip { flex: none; max-width: 46%; overflow: hidden; padding: 5px 10px; border: 1px dashed #bfcfe8; border-radius: 8px; background: #f2f7ff; color: #315583; font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.dark .correo-destino-chip { border-color: #314666; background: #1c2d46; color: #9dc1f7; }
.negar-grupo-btns { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.negar-grupo-btn {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .55rem .65rem;
  border-radius: 11px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  transition: all .15s ease;
  text-align: left;
}
.negar-grupo-btn > svg { flex-shrink: 0; color: #94a3b8; transition: color .15s ease; }
.negar-grupo-btn > span { display: flex; flex-direction: column; gap: .05rem; font-size: .72rem; font-weight: 700; color: #334155; line-height: 1.15; }
.negar-grupo-btn > span small { font-size: .6rem; font-weight: 500; color: #94a3b8; }
.negar-grupo-btn:hover { border-color: #fca5a5; }
.negar-grupo-btn:hover > svg { color: #dc2626; }
.negar-grupo-btn.active {
  border-color: #dc2626;
  background: linear-gradient(135deg, #fef2f2, #fee2e2);
  box-shadow: 0 4px 12px rgba(220,38,38,.14);
}
.negar-grupo-btn.active > svg { color: #dc2626; }
.negar-grupo-btn.active > span { color: #b91c1c; }
.negar-grupo-btn.active > span small { color: #ef8a8a; }
.negar-grupo-hint { margin: -.1rem 0 .5rem; font-size: .64rem; color: #94a3b8; font-style: italic; }
.dark .negar-grupo-btn { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); }
.dark .negar-grupo-btn > span { color: #cbd5e1; }
.dark .negar-grupo-btn > span small { color: #64748b; }
.dark .negar-grupo-btn:hover { border-color: rgba(248,113,113,.5); }
.dark .negar-grupo-btn.active { border-color: #f87171; background: rgba(220,38,38,.15); }
.dark .negar-grupo-btn.active > svg { color: #f87171; }
.dark .negar-grupo-btn.active > span { color: #fca5a5; }
.dark .negar-grupo-btn.active > span small { color: #f87171; }
.dark .negar-grupo-hint { color: #64748b; }

/* Disponibilidad de cama en el modal de aceptar */
.aceptar-disp-btns { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.aceptar-disp-btn {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .55rem .65rem;
  border-radius: 11px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  transition: all .15s ease;
  text-align: left;
}
.aceptar-disp-btn > svg { flex-shrink: 0; color: #94a3b8; transition: color .15s ease; }
.aceptar-disp-btn > span { display: flex; flex-direction: column; gap: .05rem; font-size: .72rem; font-weight: 700; color: #334155; line-height: 1.15; }
.aceptar-disp-btn > span small { font-size: .6rem; font-weight: 500; color: #94a3b8; }
.aceptar-disp-ok:hover { border-color: #86efac; }
.aceptar-disp-ok:hover > svg { color: #16a34a; }
.aceptar-disp-ok.active { border-color: #16a34a; background: linear-gradient(135deg, #f0fdf4, #dcfce7); box-shadow: 0 4px 12px rgba(22,163,74,.14); }
.aceptar-disp-ok.active > svg { color: #16a34a; }
.aceptar-disp-ok.active > span { color: #15803d; }
.aceptar-disp-ok.active > span small { color: #4ade80; }
.aceptar-disp-warn:hover { border-color: #fdba74; }
.aceptar-disp-warn:hover > svg { color: #ea580c; }
.aceptar-disp-warn.active { border-color: #ea580c; background: linear-gradient(135deg, #fff7ed, #ffedd5); box-shadow: 0 4px 12px rgba(234,88,12,.14); }
.aceptar-disp-warn.active > svg { color: #ea580c; }
.aceptar-disp-warn.active > span { color: #c2410c; }
.aceptar-disp-warn.active > span small { color: #fb923c; }
.dark .aceptar-disp-btn { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); }
.dark .aceptar-disp-btn > span { color: #cbd5e1; }
.dark .aceptar-disp-btn > span small { color: #64748b; }
.dark .aceptar-disp-ok:hover { border-color: rgba(74,222,128,.5); }
.dark .aceptar-disp-ok.active { border-color: #4ade80; background: rgba(22,163,74,.15); }
.dark .aceptar-disp-ok.active > svg { color: #4ade80; }
.dark .aceptar-disp-ok.active > span { color: #86efac; }
.dark .aceptar-disp-warn:hover { border-color: rgba(251,146,60,.5); }
.dark .aceptar-disp-warn.active { border-color: #fb923c; background: rgba(234,88,12,.15); }
.dark .aceptar-disp-warn.active > svg { color: #fb923c; }
.dark .aceptar-disp-warn.active > span { color: #fdba74; }

.accion-sin-cama-callout {
  display: flex;
  align-items: flex-start;
  gap: .5rem;
  padding: .55rem .65rem;
  border-radius: 10px;
  background: linear-gradient(135deg, #fffbeb, #fef3c7);
  border: 1px solid #fcd34d;
  font-size: .66rem;
  font-weight: 500;
  color: #92400e;
  line-height: 1.45;
}
.accion-sin-cama-callout svg { color: #d97706; margin-top: .1rem; }
.dark .accion-sin-cama-callout { background: rgba(217,119,6,.12); border-color: rgba(251,191,36,.25); color: #fcd34d; }
.dark .accion-sin-cama-callout svg { color: #fbbf24; }

/* Inputs dentro de los modales de acción: bordes suaves y foco acorde */
:deep(.accion-dialog .el-input__wrapper),
:deep(.accion-dialog .el-select__wrapper),
:deep(.accion-dialog .el-textarea__inner) {
  border-radius: 10px;
  box-shadow: 0 0 0 1px #e2e8f0 inset;
  background: #fff;
  transition: box-shadow .15s ease;
}
:deep(.accion-dialog .el-input__wrapper) { min-height: 33px; }
:deep(.accion-dialog .el-select__wrapper) { min-height: 33px; }
:deep(.accion-dialog .el-input__wrapper:hover),
:deep(.accion-dialog .el-select__wrapper:hover) { box-shadow: 0 0 0 1px #cbd5e1 inset; }
:deep(.accion-dialog .el-input__wrapper.is-focus),
:deep(.accion-dialog .el-select__wrapper.is-focused),
:deep(.accion-dialog .el-textarea__inner:focus) { box-shadow: 0 0 0 2px rgba(79,70,229,.35) inset; }
:deep(.accion-dialog .el-input__wrapper.is-disabled),
:deep(.accion-dialog .el-select__wrapper.is-disabled) { background: #f8fafc; }
:deep(.accion-dialog-negar .el-input__wrapper.is-focus),
:deep(.accion-dialog-negar .el-select__wrapper.is-focused),
:deep(.accion-dialog-negar .el-textarea__inner:focus) { box-shadow: 0 0 0 2px rgba(220,38,38,.3) inset; }
.accion-gomedisys-callout {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .55rem .65rem;
  border-radius: 10px;
  background: linear-gradient(135deg, #f5f3ff, #ede9fe);
  border: 1px solid #ddd6fe;
}
.accion-gomedisys-callout-text {
  display: flex;
  align-items: center;
  gap: .4rem;
  flex: 1;
  font-size: .66rem;
  font-weight: 600;
  color: #6d28d9;
  line-height: 1.35;
}
.accion-gomedisys-btn {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .4rem .7rem;
  border-radius: 8px;
  border: none;
  background: linear-gradient(135deg, #a78bfa, #7c3aed);
  color: #fff;
  font-size: .68rem;
  font-weight: 700;
  cursor: pointer;
  flex-shrink: 0;
  white-space: nowrap;
  transition: all .2s ease;
  box-shadow: 0 3px 10px rgba(124,58,237,.3);
}
.accion-gomedisys-btn:hover:not(.accion-gomedisys-btn-enviando) { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(124,58,237,.4); }
/* No se usa :disabled a propósito — un botón enfocado que se deshabilita
   pierde el foco de golpe y la página salta de scroll. El doble clic ya
   está bloqueado aparte, en abrirGomedisys. */
.accion-gomedisys-btn-enviando { opacity: .6; cursor: default; pointer-events: none; }
.accion-confirm-text {
  font-size: .78rem;
  color: #475569;
  line-height: 1.6;
  margin: 0;
  padding: 0 .1rem;
}

.accion-cancelar-btn {
  padding: .55rem 1.1rem;
  border-radius: 10px;
  border: 1px solid #dce7f2;
  background: #fff;
  color: #475569;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
}
.accion-cancelar-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }

.accion-confirmar-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .55rem 1.1rem;
  border-radius: 10px;
  border: none;
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
  flex-shrink: 0;
  margin-left: auto;
}
.accion-confirmar-btn:disabled { opacity: .65; cursor: default; }
.accion-confirmar-success { background: linear-gradient(135deg, #22c55e, #16a34a); box-shadow: 0 4px 14px rgba(22,163,74,.3); }
.accion-confirmar-success:hover:not(:disabled) { background: linear-gradient(135deg, #34d399, #22c55e); box-shadow: 0 6px 20px rgba(22,163,74,.4); }
.accion-confirmar-danger { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 14px rgba(220,38,38,.3); }
.accion-confirmar-danger:hover:not(:disabled) { background: linear-gradient(135deg, #f87171, #ef4444); box-shadow: 0 6px 20px rgba(220,38,38,.4); }
.accion-confirmar-info { background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4px 14px rgba(37,99,235,.3); }
.accion-confirmar-info:hover:not(:disabled) { background: linear-gradient(135deg, #60a5fa, #3b82f6); box-shadow: 0 6px 20px rgba(37,99,235,.4); }
.accion-confirmar-violet { background: linear-gradient(135deg, #818cf8, #6366f1); box-shadow: 0 4px 14px rgba(99,102,241,.3); }
.accion-confirmar-amber { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 14px rgba(217,119,6,.3); }
.accion-confirmar-amber:hover:not(:disabled) { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 6px 20px rgba(217,119,6,.4); }

/* Notas de seguimiento */
.nota-add-btn {
  display: inline-flex; align-items: center; gap: .25rem;
  margin-left: auto;
  padding: .2rem .55rem;
  border-radius: 7px;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #b45309;
  font-size: .62rem; font-weight: 700;
  cursor: pointer;
  transition: all .15s ease;
}
.nota-add-btn:hover { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.notas-list { margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed #e2e8f0; }
.notas-list-title { font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #b45309; margin: 0 0 .4rem; }
.nota-item {
  padding: .45rem .55rem;
  border-radius: 9px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  margin-bottom: .35rem;
}
.nota-item:last-child { margin-bottom: 0; }
.nota-item-head { display: flex; align-items: center; gap: .3rem; margin-bottom: .15rem; }
.nota-item-icon { color: #d97706; flex-shrink: 0; }
.nota-item-autor { font-size: .62rem; font-weight: 700; color: #92400e; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nota-item-fecha { font-size: .58rem; color: #b45309; margin-left: auto; flex-shrink: 0; }
.nota-item-texto { font-size: .68rem; color: #78350f; margin: 0; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }

:deep(.evoluciones-dialog) {
  max-height: min(760px, calc(100vh - 32px));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 20px;
  box-shadow: 0 28px 80px rgba(11,35,73,.36);
}
:deep(.evoluciones-dialog .el-dialog__header) { display: none; }
:deep(.evoluciones-dialog .el-dialog__body) { padding: 0; overflow: hidden; }
.evoluciones-modal { display: flex; flex-direction: column; max-height: min(760px, calc(100vh - 32px)); background: #f5f8fc; }
.evoluciones-modal-head {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 15px 18px;
  border-bottom: 1px solid #e7edf6;
  background: radial-gradient(circle, rgba(59,130,246,.12) 1px, transparent 1.2px) 0 0 / 13px 13px, #fff;
}
.evoluciones-modal-icon {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  border-radius: 12px;
  color: #fff;
  background: linear-gradient(135deg, #3b82f6, #16468e);
  box-shadow: 0 6px 15px rgba(37,99,235,.25);
}
.evoluciones-modal-heading { min-width: 0; flex: 1; }
.evoluciones-modal-heading h2 { margin: 0; color: #17233b; font-size: .92rem; font-weight: 850; }
.evoluciones-modal-heading p { margin: 3px 0 0; color: #7b8ba5; font-size: .68rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.evoluciones-total { padding: 4px 9px; border: 1px solid #bfdbfe; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: .62rem; font-weight: 800; white-space: nowrap; }
.evoluciones-modal-close {
  display: grid;
  place-items: center;
  width: 31px;
  height: 31px;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  background: #fff;
  color: #64748b;
  cursor: pointer;
}
.evoluciones-modal-close:hover { color: #dc2626; border-color: #fecaca; background: #fff1f2; }
.evoluciones-modal-body { flex: 1; min-height: 0; max-height: min(62vh, 560px); padding: 16px 18px; overflow-y: auto; }
.evoluciones-modal-list { display: flex; flex-direction: column; gap: 0; }
.evoluciones-modal-item { display: flex; gap: 12px; min-width: 0; }
.evoluciones-modal-rail { display: flex; justify-content: center; width: 18px; flex-shrink: 0; position: relative; }
.evoluciones-modal-item:not(:last-child) .evoluciones-modal-rail::after { content: ''; position: absolute; top: 18px; bottom: -1px; width: 2px; background: linear-gradient(#93c5fd, #dbeafe); }
.evoluciones-modal-rail span { z-index: 1; width: 11px; height: 11px; margin-top: 4px; border: 2px solid #fff; border-radius: 50%; background: #3b82f6; box-shadow: 0 0 0 2px #bfdbfe; }
.evoluciones-modal-entry { flex: 1; min-width: 0; margin-bottom: 12px; padding: 11px 13px; border: 1px solid #dbeafe; border-radius: 12px; background: linear-gradient(145deg, #fff, #f8fbff); box-shadow: 0 3px 10px rgba(37,99,235,.045); }
.evoluciones-modal-meta { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.evoluciones-modal-meta strong { display: block; color: #1e40af; font-size: .7rem; font-weight: 850; }
.evoluciones-modal-meta time { display: block; margin-top: 3px; color: #8292aa; font-size: .62rem; }
.evoluciones-entry-tag { padding: 3px 7px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-size: .54rem; font-weight: 850; white-space: nowrap; }
.evoluciones-modal-entry p { margin: 9px 0 0; color: #334155; font-size: .76rem; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.evoluciones-respuesta { margin-top: 10px; padding: 9px 11px; border: 1px solid #bbf7d0; border-left: 3px solid #16a34a; border-radius: 10px; background: #f0fdf4; }
.evoluciones-respuesta-head { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; color: #166534; font-size: .68rem; }
.evoluciones-respuesta-head time { color: #64748b; }
.evoluciones-respuesta-mail { display: inline-flex; align-items: center; gap: 3px; margin-left: auto; padding: 1px 7px; border-radius: 999px; background: #dcfce7; color: #15803d; font-weight: 700; }
.evoluciones-modal-entry .evoluciones-respuesta p { margin-top: 5px; color: #14532d; }
.evoluciones-responder { display: flex; align-items: flex-end; gap: 8px; margin-top: 10px; padding-top: 9px; border-top: 1px dashed #c9ddf8; }
.evoluciones-responder-input { flex: 1; min-width: 0; resize: vertical; padding: 7px 10px; border: 1px solid #bfdbfe; border-radius: 10px; background: #fff; color: #334155; font: inherit; font-size: .74rem; line-height: 1.5; outline: none; }
.evoluciones-responder-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
.evoluciones-responder-btn { display: inline-flex; flex-shrink: 0; align-items: center; gap: 5px; padding: 8px 14px; border: 0; border-radius: 10px; background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; font-size: .72rem; font-weight: 700; cursor: pointer; box-shadow: 0 3px 8px rgba(22,163,74,.28); }
.evoluciones-responder-btn:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
.dark .evoluciones-respuesta { border-color: #166534; background: rgba(22,163,74,.12); }
.dark .evoluciones-respuesta-head { color: #86efac; }
.dark .evoluciones-respuesta-mail { background: rgba(22,163,74,.25); color: #86efac; }
.dark .evoluciones-modal-entry .evoluciones-respuesta p { color: #dcfce7; }
.dark .evoluciones-responder { border-top-color: #2c4a75; }
.dark .evoluciones-responder-input { border-color: #2c4a75; background: #111a2b; color: #dbeafe; }
.evoluciones-adjuntos { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 9px; padding-top: 8px; border-top: 1px dashed #c9ddf8; }
.evoluciones-adjunto {
  display: inline-flex; align-items: center; gap: 5px; max-width: 100%;
  padding: 4px 9px; border: 1px solid #c9ddf8; border-radius: 8px;
  background: #f2f8ff; color: #1d4ed8; font-size: .64rem; font-weight: 700; text-decoration: none;
  transition: all .15s ease;
}
.evoluciones-adjunto:hover { background: #dbeafe; border-color: #93c5fd; }
.evoluciones-adjunto-name { overflow: hidden; max-width: 220px; text-overflow: ellipsis; white-space: nowrap; }
.evoluciones-adjunto small { color: #64748b; font-size: .56rem; font-weight: 600; }
.dark .evoluciones-adjuntos { border-top-color: #2c4a75; }
.dark .evoluciones-adjunto { border-color: #2c4a75; background: #17263f; color: #9ec8ff; }
.dark .evoluciones-adjunto:hover { background: #1f3d69; border-color: #4771ab; }
.dark .evoluciones-adjunto small { color: #7d95bb; }
.evoluciones-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 48px 18px; color: #94a3b8; text-align: center; }
.negar-attach { margin: 4px 0 8px; }
.sol-attach-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px; border: 1.5px dashed #a9ccff; border-radius: 9px;
  background: #f2f8ff; color: #0754b5; font-size: 11px; font-weight: 800;
  cursor: pointer; transition: all .15s ease;
}
.sol-attach-btn:hover { background: #e4f0ff; border-color: #5b9dff; }
.sol-attach-hint { margin-left: 8px; color: #90a6c4; font-size: 9.5px; }
.sol-attach-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.sol-attach-chip {
  display: inline-flex; align-items: center; gap: 6px; max-width: 100%;
  padding: 4px 8px; border: 1px solid #d4e2f5; border-radius: 8px;
  background: #fff; color: #2d4a73; font-size: 10.5px; font-weight: 600;
}
.sol-attach-name { overflow: hidden; max-width: 180px; text-overflow: ellipsis; white-space: nowrap; }
.sol-attach-del {
  display: grid; place-items: center; width: 16px; height: 16px; flex-shrink: 0;
  border: 0; border-radius: 50%; background: #fee2e2; color: #dc2626; cursor: pointer;
}
.sol-attach-del:hover { background: #fecaca; }
.dark .sol-attach-btn { border-color: #31527c; background: #172b47; color: #93c5fd; }
.dark .sol-attach-btn:hover { border-color: #4d8ef7; background: #1e3a5f; }
.dark .sol-attach-hint { color: #6d84a8; }
.dark .sol-attach-chip { border-color: #314666; background: #1c2d46; color: #bcd5f7; }
.dark .sol-attach-del { background: #4c1d24; color: #fda4af; }
.evoluciones-empty > svg { color: #60a5fa; }
.evoluciones-empty strong { color: #334155; font-size: .84rem; }
.evoluciones-empty span { max-width: 320px; font-size: .68rem; line-height: 1.5; }
.evoluciones-modal-footer { display: flex; align-items: center; justify-content: space-between; padding: 10px 18px 14px; border-top: 1px solid #e7edf6; background: #fff; }
.evoluciones-modal-footer > span { display: inline-flex; align-items: center; gap: 5px; color: #94a3b8; font-size: .62rem; }
.evoluciones-modal-done { min-height: 32px; padding: 0 14px; border: 0; border-radius: 9px; background: linear-gradient(135deg, #2563eb, #16468e); color: white; font-size: .68rem; font-weight: 800; cursor: pointer; }
.dark :deep(.evoluciones-dialog) { background: #0f172a; }
.dark .evoluciones-modal { background: #0b1220; }
.dark .evoluciones-modal-head, .dark .evoluciones-modal-footer { border-color: #263352; background-color: #111a2b; }
.dark .evoluciones-modal-heading h2 { color: #e2e8f0; }
.dark .evoluciones-modal-heading p { color: #94a3b8; }
.dark .evoluciones-modal-close { border-color: #334155; background: #172033; color: #94a3b8; }
.dark .evoluciones-total { border-color: #31527c; background: #172b47; color: #93c5fd; }
.dark .evoluciones-modal-entry { border-color: #263f63; background: linear-gradient(145deg, #15233a, #111a2b); }
.dark .evoluciones-modal-meta strong { color: #bfdbfe; }
.dark .evoluciones-modal-meta time, .dark .evoluciones-modal-footer > span { color: #94a3b8; }
.dark .evoluciones-entry-tag { background: #1e3a5f; color: #93c5fd; }
.dark .evoluciones-modal-entry p { color: #dbeafe; }
.dark .evoluciones-empty strong { color: #e2e8f0; }
@media (max-width: 600px) {
  .evoluciones-modal-head { gap: 8px; padding: 12px; }
  .evoluciones-modal-icon { width: 32px; height: 32px; }
  .evoluciones-total { font-size: .54rem; padding: 4px 6px; }
  .evoluciones-modal-body { padding: 12px; }
  .evoluciones-modal-meta { flex-direction: column; gap: 6px; }
  .evoluciones-modal-footer { padding: 9px 12px 12px; }
}

.notas-list-modal { margin-top: .55rem; }
.notas-list-scroll { max-height: 160px; overflow-y: auto; padding-right: .15rem; }
.notas-list-scroll::-webkit-scrollbar { width: 5px; }
.notas-list-scroll::-webkit-scrollbar-thumb { background: #fde68a; border-radius: 4px; }
/* Ficha de copiado para Gomedisys */
.ficha-list {
  display: flex;
  flex-direction: column;
  gap: .35rem;
  max-height: 46vh;
  overflow-y: auto;
}
.ficha-item {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .4rem .45rem .4rem .6rem;
  border-radius: 9px;
  background: #f8fafc;
  border: 1px solid #eef2f7;
}
.ficha-item-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .05rem; }
.ficha-item-label {
  font-size: .56rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .03em;
}
.ficha-item-value {
  font-size: .72rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  word-break: break-word;
}
.ficha-copy-btn {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .32rem .6rem;
  border-radius: 7px;
  border: 1px solid #ddd6fe;
  background: #fff;
  color: #6d28d9;
  font-size: .62rem;
  font-weight: 700;
  cursor: pointer;
  flex-shrink: 0;
  white-space: nowrap;
  transition: all .15s ease;
}
.ficha-copy-btn:hover:not(:disabled) { background: #f5f3ff; border-color: #c4b5fd; }
.ficha-copy-btn:disabled { opacity: .45; cursor: default; }
.ficha-copy-btn-ok { background: #dcfce7 !important; border-color: #86efac !important; color: #16a34a !important; }
.accion-confirmar-violet:hover:not(:disabled) { background: linear-gradient(135deg, #a5b4fc, #818cf8); box-shadow: 0 6px 20px rgba(99,102,241,.4); }
.accion-confirmar-amber { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 14px rgba(217,119,6,.3); }
.accion-confirmar-amber:hover:not(:disabled) { background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 6px 20px rgba(217,119,6,.4); }
</style>

<style>
/* ── Menú de acciones de la fila (teleportado al body por Element Plus,
   por eso va sin "scoped" — el estilo normal no le llegaría) ── */
.sol-acciones-menu {
  min-width: 190px;
  border-radius: 14px !important;
  border: 1.5px solid #d7dde6 !important;
  box-shadow: 0 14px 38px rgba(15, 23, 42, .18) !important;
  overflow: hidden;
}
.sol-acciones-menu .el-dropdown-menu {
  padding: 6px !important;
}
.sol-acciones-menu .el-dropdown-menu__item {
  border-radius: 8px;
  margin: 1px 0;
  padding: .4rem .6rem;
  font-size: .78rem;
  font-weight: 500;
  line-height: 1.3;
  gap: .45rem;
}
.sol-acciones-menu .el-dropdown-menu__item i,
.sol-acciones-menu .el-dropdown-menu__item svg {
  font-size: .85rem;
  margin-right: 0;
}
.sol-acciones-menu .el-dropdown-menu__item--divided {
  margin-top: 5px;
  border-top-color: #eef1f5;
}
.sol-acciones-menu .el-dropdown-menu__item--divided::before {
  margin-bottom: 5px;
}
.sol-acciones-menu .sol-accion-success { color: #16a34a; }
.sol-acciones-menu .sol-accion-success:hover { background: #f0fdf4; color: #15803d; }
.sol-acciones-menu .sol-accion-info { color: #2563eb; }
.sol-acciones-menu .sol-accion-info:hover { background: #eff6ff; color: #1d4ed8; }
.sol-acciones-menu .sol-accion-violet { color: #6366f1; }
.sol-acciones-menu .sol-accion-violet:hover { background: #eef2ff; color: #4f46e5; }
.sol-acciones-menu .sol-accion-teal { color: #0d9488; }
.sol-acciones-menu .sol-accion-teal:hover { background: #f0fdfa; color: #0f766e; }
.sol-acciones-menu .sol-accion-amber { color: #d97706; }
.sol-acciones-menu .sol-accion-amber:hover { background: #fffbeb; color: #b45309; }
.sol-acciones-menu .sol-accion-danger { color: #dc2626; }
.sol-acciones-menu .sol-accion-danger:hover { background: #fef2f2; color: #b91c1c; }
.sol-acciones-menu .sol-accion-neutral { color: #64748b; }
.sol-acciones-menu .sol-accion-neutral:hover { background: #f8fafc; color: #334155; }

/* ── Dropdown de motivos de negación (teleportado al body) ── */
.negar-motivo-popper.el-select-dropdown {
  border-radius: 13px !important;
  border: 1.5px solid #e2e8f0 !important;
  box-shadow: 0 18px 45px rgba(15,23,42,.18) !important;
  padding: 6px !important;
  max-width: 420px;
}
.negar-motivo-popper .el-select-dropdown__list { padding: 0 !important; }
.negar-motivo-popper .el-select-dropdown__item {
  position: relative;
  border-radius: 8px;
  height: 32px;
  line-height: 32px;
  padding: 0 .65rem 0 1.5rem;
  font-size: .74rem;
  font-weight: 500;
  color: #475569;
  letter-spacing: .01em;
}
.negar-motivo-popper .el-select-dropdown__item::before {
  content: '';
  position: absolute;
  left: .55rem;
  top: 50%;
  transform: translateY(-50%);
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #cbd5e1;
  transition: all .15s ease;
}
.negar-motivo-popper .el-select-dropdown__item:not(:last-child) { margin-bottom: 2px; }
.negar-motivo-popper .el-select-dropdown__item.is-hovering { background: #fef2f2; color: #b91c1c; }
.negar-motivo-popper .el-select-dropdown__item.is-hovering::before { background: #f87171; }
.negar-motivo-popper .el-select-dropdown__item.is-selected { background: rgba(220,38,38,.08); color: #b91c1c; font-weight: 700; }
.negar-motivo-popper .el-select-dropdown__item.is-selected::before { background: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.15); }
.negar-motivo-popper .el-select-dropdown__item .el-select-dropdown__item-icon { color: #dc2626; font-size: .8rem; }
.dark .negar-motivo-popper.el-select-dropdown { background: #1e293b !important; border-color: #334155 !important; }
.dark .negar-motivo-popper .el-select-dropdown__item { color: #cbd5e1 !important; }
.dark .negar-motivo-popper .el-select-dropdown__item::before { background: #475569; }
.dark .negar-motivo-popper .el-select-dropdown__item.is-hovering { background: rgba(248,113,113,.12) !important; color: #fca5a5 !important; }
.dark .negar-motivo-popper .el-select-dropdown__item.is-hovering::before { background: #f87171; }
.dark .negar-motivo-popper .el-select-dropdown__item.is-selected { background: rgba(220,38,38,.2) !important; color: #fca5a5 !important; }
.dark .negar-motivo-popper .el-select-dropdown__item.is-selected::before { background: #f87171; box-shadow: 0 0 0 3px rgba(248,113,113,.18); }
.dark .negar-motivo-popper .el-select-dropdown__item .el-select-dropdown__item-icon { color: #f87171; }
</style>
