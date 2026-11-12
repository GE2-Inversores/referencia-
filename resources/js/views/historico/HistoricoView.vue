<template>
  <div class="ph-solicitudes h-full flex flex-col gap-2 p-3 sm:p-4 overflow-hidden">

    <!-- ── Stat cards ── -->
    <div class="sol-stats-bar sol-stats-bar-historico shrink-0">
      <StatCard
        variant="pastel" tone="violet" solid-icon
        label="Total histórico" :value="displayStats[0]"
        :comparacion="deltaTotal"
        :icon="HistoryIcon" :sparkline="serieDiasTotal"
      />
      <StatCard
        variant="pastel" tone="success" solid-icon
        label="Completadas" :value="displayStats[1]"
        :comparacion="deltaCompletadas"
        :icon="CheckCircleIcon" :sparkline="serieDiasCompletadas"
      />
      <StatCard
        variant="pastel" tone="danger" solid-icon
        label="Negadas" :value="displayStats[2]"
        :comparacion="deltaNegadas"
        :icon="XCircleIcon" :sparkline="serieDiasNegadas"
      />
    </div>

    <!-- ── Tabs + Búsqueda ── -->
    <div class="sol-filter-bar shrink-0">
      <div class="sol-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="sol-tab"
          :class="{ 'sol-tab-active': tabActiva === tab.value }"
          @click="cambiarTab(tab.value)"
        >
          <span v-if="tab.value !== 'todas'" class="sol-tab-dot" :class="'sol-tab-dot--' + tab.value"></span>
          {{ tab.label }}
          <span class="sol-tab-count">{{ tab.count }}</span>
        </button>
      </div>
      <div class="sol-filter-spacer"></div>
      <el-input
        v-model="filtro.buscar"
        class="sol-search sol-bar-search"
        placeholder="Buscar por cédula o nombre"
        aria-label="Buscar en el histórico por cédula o nombre"
        clearable
        size="small"
      >
        <template #prefix>
          <component :is="SearchIcon" class="w-3.5 h-3.5 text-gray-400" />
        </template>
      </el-input>
      <div class="sol-chips-actions">
        <el-tooltip content="Actualizar" placement="top">
          <button class="sol-icon-btn" @click="cargar">
            <component :is="RefreshIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': cargando }" />
          </button>
        </el-tooltip>
        <el-tooltip content="Exportar Excel" placement="top">
          <button class="sol-icon-btn" :disabled="solicitudesFiltradas.length === 0 || exportando" @click="exportarExcel">
            <component :is="DownloadIcon" class="w-3.5 h-3.5" />
          </button>
        </el-tooltip>
      </div>
      <div ref="filtrosWrap" class="sol-filtros-wrap">
        <button class="sol-filtros-btn" :class="{ 'sol-filtros-btn-active': filtrosAbiertos || conteoFiltros > 0 }" @click="filtrosAbiertos = !filtrosAbiertos">
          <component :is="SlidersIcon" class="w-3.5 h-3.5" />
          Filtros
          <span v-if="conteoFiltros > 0" class="sol-filtros-badge">{{ conteoFiltros }}</span>
        </button>
        <Transition name="bulk-slide">
          <div v-if="filtrosAbiertos" class="sol-filtros-panel">
            <DateRangeFilter v-model="rangoFechas" />
            <el-select v-model="filtroClinica" placeholder="Todas las clínicas" clearable filterable size="small" class="sol-select">
              <el-option v-for="c in clinicasUnicas" :key="c.id" :label="c.nombre" :value="c.id" />
            </el-select>
            <el-select v-model="filtroEspecialidad" placeholder="Todas las especialidades" clearable filterable size="small" class="sol-select">
              <el-option v-for="e in especialidadesUnicas" :key="e" :label="e" :value="e" />
            </el-select>
            <el-select v-model="filtroEps" placeholder="Todas las aseguradoras" clearable filterable size="small" class="sol-select">
              <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
            </el-select>
            <el-select v-model="filtroEstado" placeholder="Todos los estados" clearable size="small" class="sol-select" @update:model-value="onEstadoSelect">
              <el-option label="Completado" value="completado" />
              <el-option label="Negado" value="negado" />
              <el-option label="Cancelado" value="cancelado" />
              <el-option label="Aceptado" value="en_espera" />
              <el-option label="Pendiente" value="pendiente_cama" />
              <el-option label="Nueva solicitud" value="pendiente" />
            </el-select>
            <el-select v-model="filtroOrigen" placeholder="Todos los orígenes" clearable size="small" class="sol-select">
              <el-option label="Correo" value="correo" />
              <el-option label="Aplicativo" value="aplicativo" />
            </el-select>
            <el-select v-model="sortKey" aria-label="Ordenar por" size="small">
              <el-option label="Ordenar por fecha" value="fecha" />
              <el-option label="Ordenar por paciente" value="paciente" />
              <el-option label="Ordenar por clínica" value="clinica" />
              <el-option label="Ordenar por especialidad" value="especialidad" />
            </el-select>
            <el-select v-model="sortDir" aria-label="Dirección del orden" size="small">
              <el-option label="Descendente" value="desc" />
              <el-option label="Ascendente" value="asc" />
            </el-select>
            <button v-if="hayFiltrosActivos" class="sol-limpiar" @click="limpiarFiltros">Limpiar filtros</button>
          </div>
        </Transition>
      </div>
    </div>

    <!-- ── Tabla ── -->
    <div class="flex-1 overflow-hidden sol-table-panel">
      <!-- Loading -->
      <div v-if="cargando" class="sol-table-loading">
        <div v-for="i in 6" :key="i" class="sol-table-row-skeleton">
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

      <!-- Lista en tarjetas -->
      <div v-else class="flex flex-col h-full overflow-hidden">
        <div class="hist-selectbar shrink-0">
          <label class="hist-selectbar-label" title="Seleccionar todas las de esta página para exportar">
            <el-checkbox :model-value="paginaSeleccionada" @change="toggleSeleccionPagina" />
            Seleccionar página para exportar
          </label>
          <span v-if="seleccionadas.size" class="hist-selectbar-count">{{ seleccionadas.size }} seleccionadas</span>
        </div>
        <div class="hist-card-scroll custom-scrollbar">
          <TransitionGroup name="sol-row" tag="div" class="hist-card-list" role="list" aria-label="Histórico de solicitudes">
            <article
              v-for="s in solicitudesPaginadas"
              :id="`sol-row-${s.id}`"
              :key="s.id"
              role="listitem"
              class="hist-card"
              :class="[
                { 'hist-card-resaltada': resaltarId === s.id, 'hist-card-urgent': s.urgencia_vital },
                !s.urgencia_vital ? `hist-card--${s.estado}` : ''
              ]"
            >
              <div class="hist-card-identity">
                <el-checkbox :model-value="seleccionadas.has(s.id)" @change="toggleSeleccion(s.id)" />
                <div class="hist-card-avatar"
                  :style="{
                    background: s.estado === 'pendiente' ? '#fef3c7' : s.estado === 'pendiente_cama' ? '#ffedd5' : s.estado === 'en_espera' ? '#dbeafe' : s.estado === 'completado' ? '#dcfce7' : '#fee2e2',
                    color: s.estado === 'pendiente' ? '#d97706' : s.estado === 'pendiente_cama' ? '#ea580c' : s.estado === 'en_espera' ? '#2563eb' : s.estado === 'completado' ? '#16a34a' : '#dc2626'
                  }">
                  {{ inicialesPaciente(s) }}
                </div>
                <div class="hist-card-idtext">
                  <button type="button" class="hist-card-name" :class="{ 'hist-card-name-resaltada': resaltarId === s.id }" :title="nombreCompleto(s)" @click="verDetalle(s)">{{ nombreCompleto(s) }}</button>
                  <p class="hist-card-doc">{{ s.tipo_documento }} {{ s.numero_documento }}</p>
                </div>
              </div>
              <div class="hist-card-block hist-card-block--clinic hist-card-divider">
                <span class="hist-card-label">Clínica emisora</span>
                <p class="hist-card-clinic" :title="s.clinica?.nombre">{{ s.clinica?.nombre ?? '—' }}</p>
                <p class="hist-card-remitente" v-if="s.quien_remitente || s.telefono_contacto" :title="[s.quien_remitente, s.telefono_contacto].filter(Boolean).join(' · ')">
                  {{ s.quien_remitente || '—' }}<span v-if="s.telefono_contacto"> · {{ s.telefono_contacto }}</span>
                </p>
              </div>
              <div class="hist-card-block hist-card-block--clinical hist-card-divider">
                <span class="hist-card-label">Especialidad</span>
                <p class="hist-card-specialty" :title="s.especialidad_requerida">{{ s.especialidad_requerida }}</p>
                <span class="hist-card-label hist-card-label-gap">Diagnóstico</span>
                <p v-if="s.diagnosticos?.length" class="hist-card-dx" :title="s.diagnosticos[0].descripcion">
                  <strong class="hist-card-dx-code">{{ s.diagnosticos[0].codigo_cie10 }}</strong>
                  {{ s.diagnosticos[0].descripcion }}<span v-if="s.diagnosticos.length > 1" class="sol-dx-more"> +{{ s.diagnosticos.length - 1 }}</span>
                </p>
                <p v-else class="hist-card-dx" :title="s.diagnostico">{{ s.diagnostico || '—' }}</p>
              </div>
              <div class="hist-card-state hist-card-divider">
                <span class="sol-table-status" :class="{
                  'sol-status-pending': s.estado === 'pendiente',
                  'sol-status-cama': s.estado === 'pendiente_cama',
                  'sol-status-waiting': s.estado === 'en_espera',
                  'sol-status-completed': s.estado === 'completado',
                  'sol-status-rejected': s.estado === 'negado',
                  'sol-status-cancelled': s.estado === 'cancelado',
                }">
                  <span class="sol-status-dot"></span>
                  {{ estadoLabel(s.estado) }}
                </span>
                <p class="hist-card-fecha">{{ formatFecha(s.created_at) }} · {{ horaDeFecha(s.created_at) }}</p>
                <span class="sol-tiempo-chip" :class="{ 'sol-tiempo-chip--lento': tiempoLento(s) }">{{ tiempoResolucion(s) }}</span>
              </div>
              <div class="hist-card-actions">
                <el-button circle size="small" title="Ver detalle" aria-label="Ver detalle de la solicitud" @click="verDetalle(s)">
                  <component :is="EyeIcon" class="w-3.5 h-3.5" />
                </el-button>
                <el-button circle size="small" title="Descargar PDF" aria-label="Descargar PDF" @click="exportarPdfFila(s)">
                  <component :is="FileDownIcon" class="w-3.5 h-3.5" />
                </el-button>
                <el-dropdown trigger="click" placement="bottom-end">
                  <el-button circle size="small" aria-label="Más acciones">
                    <component :is="MoreVerticalIcon" class="w-3.5 h-3.5" />
                  </el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item @click="verDetalle(s)">
                        <component :is="EyeIcon" class="w-3.5 h-3.5 mr-1.5" /> Ver detalle
                      </el-dropdown-item>
                      <el-dropdown-item @click="verHistoria(s)">
                        <component :is="FileTextIcon" class="w-3.5 h-3.5 mr-1.5" /> Historia clínica
                      </el-dropdown-item>
                      <el-dropdown-item @click="exportarPdfFila(s)">
                        <component :is="FileDownIcon" class="w-3.5 h-3.5 mr-1.5" /> Descargar PDF
                      </el-dropdown-item>
                      <el-dropdown-item @click="abrirCambiar(s)">
                        <component :is="RotateCcwIcon" class="w-3.5 h-3.5 mr-1.5" /> Cambiar estado
                      </el-dropdown-item>
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
            Mostrando {{ (paginaActual - 1) * itemsPorPagina + 1 }}–{{ Math.min(paginaActual * itemsPorPagina, solicitudesFiltradas.length) }}
            de {{ solicitudesFiltradas.length }}
          </span>
          <div class="sol-pagination-controls">
            <button class="sol-pagination-btn" :disabled="paginaActual === 1" @click="paginaActual--">
              <component :is="ChevronLeftIcon" class="w-3.5 h-3.5" />
            </button>
            <template v-for="(p, idx) in paginasVisibles" :key="idx">
              <span v-if="p === '…'" class="sol-pagination-dots">…</span>
              <button v-else class="sol-pagination-num" :class="{ 'sol-pagination-num-active': p === paginaActual }" @click="paginaActual = p">
                {{ p }}
              </button>
            </template>
            <button class="sol-pagination-btn" :disabled="paginaActual === totalPaginas" @click="paginaActual++">
              <component :is="ChevronRightIcon" class="w-3.5 h-3.5" />
            </button>
            <el-select v-model="itemsPorPagina" size="small" class="sol-por-pagina">
              <el-option :value="10" label="10 por página" />
              <el-option :value="15" label="15 por página" />
              <el-option :value="25" label="25 por página" />
              <el-option :value="50" label="50 por página" />
            </el-select>
          </div>
        </div>
      </div>
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
              <p class="detalle-head-sub">ID #{{ solicitudSeleccionada.id }} · {{ formatFecha(solicitudSeleccionada.fecha) }} · {{ formatHora(solicitudSeleccionada.hora) }}</p>
            </div>
            <span class="detalle-head-badge" :class="'estado-' + solicitudSeleccionada.estado">
              {{ estadoLabel(solicitudSeleccionada.estado) }}
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
                  <div class="data-row"><span>Servicio actual</span><strong>{{ solicitudSeleccionada.servicio_ubicacion_actual }}</strong></div>
                </div>
              </div>
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-amber"><component :is="BuildingIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-amber">Remisión</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Institución</span><strong>{{ solicitudSeleccionada.clinica?.nombre ?? '—' }}</strong></div>
                  <div v-if="solicitudSeleccionada.quien_remitente" class="data-row"><span>Remite</span><strong>{{ solicitudSeleccionada.quien_remitente }}</strong></div>
                  <div v-if="solicitudSeleccionada.telefono_contacto" class="data-row"><span>Teléfono</span><strong>{{ solicitudSeleccionada.telefono_contacto }}</strong></div>
                  <div v-if="solicitudSeleccionada.correo_contacto" class="data-row"><span>Correo</span><strong>{{ solicitudSeleccionada.correo_contacto }}</strong></div>
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

            <!-- Historia clínica a ancho completo -->
            <div class="detalle-card mb-1.5">
              <div class="detalle-card-head">
                <span class="detalle-card-icon detalle-card-icon-blue"><component :is="ClipboardListIcon" class="w-3.5 h-3.5" /></span>
                <p class="detalle-card-title detalle-card-title-blue">Historia clínica</p>
              </div>
              <div class="card-body">
                <p class="card-text-sm card-text-clamp">{{ solicitudSeleccionada.resumen_historia_clinica }}</p>
                <button
                  v-if="(solicitudSeleccionada.resumen_historia_clinica?.length ?? 0) > 180"
                  class="leer-mas-btn"
                  @click="abrirHistoriaClinica"
                >Leer más</button>
              </div>
            </div>

            <!-- Seguimiento + Soportes en 2 columnas -->
            <div class="grid grid-cols-2 gap-2">
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-slate"><component :is="ClockIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-slate">Seguimiento</p>
                </div>
                <div class="card-body">
                  <div v-for="(paso, i) in pasosSeguimiento" :key="i" class="timeline-item">
                    <div class="timeline-connector">
                      <div class="timeline-dot" :class="{ 'timeline-dot-done': paso.hecho, 'timeline-dot-negada': paso.tipo === 'negada' }">
                        <component :is="ICONO_PASO[paso.tipo]" class="w-2.5 h-2.5" />
                      </div>
                      <div v-if="i < pasosSeguimiento.length - 1" class="timeline-line" :class="{ 'timeline-line-done': paso.hecho }"></div>
                    </div>
                    <div class="timeline-text">
                      <p class="timeline-titulo" :class="{ 'timeline-titulo-pending': !paso.hecho }">{{ paso.titulo }}</p>
                      <p class="timeline-fecha">{{ paso.fecha }}</p>
                    </div>
                  </div>
                  <p v-if="solicitudSeleccionada.observaciones_respuesta" class="timeline-obs">{{ solicitudSeleccionada.observaciones_respuesta }}</p>
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
                  <el-select v-model="formCambiar.eps" filterable placeholder="Seleccione la EPS" class="w-full" popper-class="negar-motivo-popper">
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
                    <el-input v-model="formCambiar.lugar_llegada" placeholder="Ej: Urgencias" />
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

    <el-dialog v-model="modalHistoriaClinica" width="620px" class="historia-dialog" append-to-body align-center>
      <template #header>
        <div class="historia-dialog-header">
          <component :is="FileTextIcon" class="w-5 h-5" />
          <div>
            <p>Historia clínica</p>
            <span>{{ solicitudSeleccionada ? nombreCompleto(solicitudSeleccionada) : '' }}</span>
          </div>
        </div>
      </template>
      <p class="historia-dialog-text">{{ solicitudSeleccionada?.resumen_historia_clinica }}</p>
    </el-dialog>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue';
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
  Shield as ShieldIcon,
  User as UserIcon,
  UserX as UserXIcon,
  Users as UsersIcon,
  Building2 as BuildingIcon,
  Hourglass as HourglassIcon,
  FileDown as FileDownIcon,
  History as HistoryIcon,
  SlidersHorizontal as SlidersIcon,
  MoreVertical as MoreVerticalIcon,
  RotateCcw as RotateCcwIcon,
  Loader2 as Loader2Icon,
  StickyNote as StickyNoteIcon,
} from '@lucide/vue';
import http from '@/plugins/axios';
import { exportarSolicitudesExcel } from '@/lib/exportarSolicitudesExcel';
import { EPS_LIST } from '@/data/referencia';
import { useAuthStore } from '@/stores/auth';
import StatCard from '@/components/ui/StatCard.vue';
import DateRangeFilter from '@/components/ui/DateRangeFilter.vue';

const route = useRoute();
const router = useRouter();

interface Clinica {
  id: number;
  nombre: string;
}

interface Solicitud {
  id: number;
  clinica_id: number;
  clinica?: Clinica;
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
  servicio_ubicacion_actual: string;
  servicio_remision?: string;
  quien_remitente?: string;
  telefono_contacto?: string;
  correo_contacto?: string;
  resumen_historia_clinica: string;
  via_contacto?: string;
  gestante?: boolean;
  condicion_especial?: string;
  urgencia_vital?: boolean;
  observaciones?: string;
  estado: 'pendiente' | 'pendiente_cama' | 'en_espera' | 'completado' | 'negado' | 'cancelado';
  codigo_aceptacion?: string;
  hora_respuesta?: string;
  sede?: string;
  motivo_negacion?: string;
  grupo_negacion?: string;
  numero_ingreso?: number;
  nombre_quien_responde?: string;
  observaciones_respuesta?: string;
  origen?: string;
  adjuntos?: { id: number; nombre_original: string; mime_type: string; tamano: number }[];
  eventos?: { id: number; tipo: string; titulo: string; descripcion?: string; created_at: string }[];
  created_at: string;
  updated_at?: string;
}

const solicitudes = ref<Solicitud[]>([]);
const cargando = ref(false);
// Los filtros se persisten por usuario — con una llave compartida, lo que
// eligiera una sesión se le aplicaba también a otra cuenta en el mismo PC.
const uidFiltros = useAuthStore().user?.id ?? 0;
const filtro = useStorage(`historico-filtro-u${uidFiltros}`, { buscar: '' });
const buscarDebounced = ref(filtro.value.buscar);
const updateBuscarDebounced = useDebounceFn((val: string) => { buscarDebounced.value = val; }, 300);
watch(() => filtro.value.buscar, (val) => updateBuscarDebounced(val));
const tabActiva = useStorage<'todas' | 'completado' | 'negado' | 'cancelado'>(`historico-tab-u${uidFiltros}`, 'todas');

const sortKey = ref<'paciente' | 'clinica' | 'especialidad' | 'fecha'>('fecha');
const sortDir = ref<'asc' | 'desc'>('desc');
const resaltarId = ref<number | null>(null);
const paginaActual = ref(1);
const itemsPorPagina = ref(10);

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

const rangoFechas = ref<[string, string] | null>(null);
const filtroClinica = ref<number | ''>('');
const filtroEspecialidad = ref('');
const filtroEps = ref('');
const filtroEstado = ref<'' | 'completado' | 'negado' | 'cancelado' | 'en_espera' | 'pendiente' | 'pendiente_cama'>('');
const filtroOrigen = ref<'' | 'correo' | 'aplicativo'>('');
const seleccionadas = ref<Set<number>>(new Set());

watch([itemsPorPagina, rangoFechas, filtroClinica, filtroEspecialidad, filtroEps, filtroEstado, buscarDebounced], () => {
  paginaActual.value = 1;
});

const modalDetalle = ref(false);
const modalHistoriaClinica = ref(false);
const modalPdf = ref(false);
const solicitudSeleccionada = ref<Solicitud | null>(null);
const adjuntoActivo = ref<{ id: number; nombre_original: string; mime_type: string; tamano: number } | null>(null);

const resumen = computed(() => ({
  total: solicitudes.value.length,
  completadas: solicitudes.value.filter(s => s.estado === 'completado').length,
  negadas: solicitudes.value.filter(s => s.estado === 'negado').length,
  canceladas: solicitudes.value.filter(s => s.estado === 'cancelado').length,
}));

const tabs = computed<{ label: string; value: 'todas' | 'completado' | 'negado' | 'cancelado'; count: number }[]>(() => [
  { label: 'Todas', value: 'todas', count: resumen.value.total },
  { label: 'Completadas', value: 'completado', count: resumen.value.completadas },
  { label: 'Negadas', value: 'negado', count: resumen.value.negadas },
  { label: 'Canceladas', value: 'cancelado', count: resumen.value.canceladas },
]);

const isoDia = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

interface EvoBucket { cat: string; completadas: number; negadas: number; }

/** Conteo real de completadas/negadas por día en los últimos 14 días. */
function bucketsEvolucion(): EvoBucket[] {
  const ahora = new Date();
  const DIAS = 14;
  const keys = Array.from({ length: DIAS }, (_, i) => {
    const d = new Date(ahora); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() - (DIAS - 1 - i));
    return { key: isoDia(d), cat: d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short' }) };
  });
  const buckets: EvoBucket[] = keys.map(k => ({ cat: k.cat, completadas: 0, negadas: 0 }));
  const idx = new Map(keys.map((k, i) => [k.key, i]));
  for (const s of solicitudes.value) {
    if (!s.created_at) continue;
    const f = new Date(s.created_at);
    if (isNaN(f.getTime())) continue;
    const i = idx.get(isoDia(f));
    if (i == null) continue;
    if (s.estado === 'completado') buckets[i].completadas++;
    else if (s.estado === 'negado') buckets[i].negadas++;
  }
  return buckets;
}

const evolucionDia = computed(() => bucketsEvolucion());

const serieDiasTotal = computed(() => evolucionDia.value.map(b => b.completadas + b.negadas));
const serieDiasCompletadas = computed(() => evolucionDia.value.map(b => b.completadas));
const serieDiasNegadas = computed(() => evolucionDia.value.map(b => b.negadas));

/** Variación últimos 7 días vs los 7 anteriores (por created_at). */
function deltaUltimos7(estado?: 'completado' | 'negado'): string {
  const SEMANA = 7 * 24 * 60 * 60 * 1000;
  const ahora = Date.now();
  let act = 0, prev = 0;
  for (const s of solicitudes.value) {
    if (estado && s.estado !== estado) continue;
    const diff = ahora - new Date(s.created_at).getTime();
    if (diff >= 0 && diff < SEMANA) act++;
    else if (diff >= SEMANA && diff < 2 * SEMANA) prev++;
  }
  if (!prev) return act ? `↑ ${act} últimos 7 días` : 'Sin movimiento';
  const pct = Math.round(((act - prev) / prev) * 100);
  return `${pct >= 0 ? '↑' : '↓'} ${Math.abs(pct)}% vs. período anterior`;
}
const deltaTotal = computed(() => deltaUltimos7());
const deltaCompletadas = computed(() => deltaUltimos7('completado'));
const deltaNegadas = computed(() => deltaUltimos7('negado'));

const displayStats = ref([0, 0, 0]);

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

const clinicasUnicas = computed(() => {
  const map = new Map<number, string>();
  for (const s of solicitudes.value) {
    if (s.clinica) map.set(s.clinica.id, s.clinica.nombre);
  }
  return [...map.entries()].map(([id, nombre]) => ({ id, nombre })).sort((a, b) => a.nombre.localeCompare(b.nombre));
});

const especialidadesUnicas = computed(() =>
  [...new Set(solicitudes.value.map(s => s.especialidad_requerida).filter(Boolean))].sort((a, b) => a.localeCompare(b))
);

const hayFiltrosActivos = computed(() =>
  !!filtro.value.buscar || tabActiva.value !== 'todas' || !!rangoFechas.value ||
  filtroClinica.value !== '' || filtroEspecialidad.value !== '' || filtroEps.value !== '' || filtroEstado.value !== '' || filtroOrigen.value !== ''
);

/** Cuántos filtros del panel están activos (el tab de estado no cuenta — ya se ve en los chips). */
const conteoFiltros = computed(() =>
  [filtroClinica.value !== '', filtroEspecialidad.value !== '', filtroEps.value !== '', !!rangoFechas.value, filtroEstado.value !== '', filtroOrigen.value !== '']
    .filter(Boolean).length
);

/** El select de estados excluye al chip activo: elegir un estado vuelve a "Todas" los chips. */
function onEstadoSelect(v: string | undefined) {
  filtroEstado.value = (v ?? '') as typeof filtroEstado.value;
  if (v) tabActiva.value = 'todas';
}

const solicitudesFiltradas = computed(() => {
  let result = solicitudes.value.filter(s => {
    if (tabActiva.value !== 'todas' && s.estado !== tabActiva.value) return false;
    if (filtroEstado.value !== '' && s.estado !== filtroEstado.value) return false;
    if (filtroClinica.value !== '' && s.clinica_id !== filtroClinica.value) return false;
    if (filtroEspecialidad.value !== '' && s.especialidad_requerida !== filtroEspecialidad.value) return false;
    if (filtroEps.value !== '' && s.eps !== filtroEps.value) return false;
    if (filtroOrigen.value !== '' && (s.origen || 'aplicativo') !== filtroOrigen.value) return false;
    if (rangoFechas.value) {
      const f = s.created_at?.slice(0, 10) ?? '';
      if (f < rangoFechas.value[0] || f > rangoFechas.value[1]) return false;
    }
    const texto = buscarDebounced.value.trim().toLowerCase();
    const cedulaBuscada = /^[\d.\s-]+$/.test(texto) ? texto.replace(/[.\s-]/g, '') : '';
    const coincideTexto = !texto ||
      nombreCompleto(s).toLowerCase().includes(texto) ||
      s.eps.toLowerCase().includes(texto) ||
      s.especialidad_requerida.toLowerCase().includes(texto) ||
      s.clinica?.nombre.toLowerCase().includes(texto) ||
      s.numero_documento.toLowerCase().includes(texto) ||
      (cedulaBuscada !== '' && s.numero_documento.replace(/[.\s-]/g, '').includes(cedulaBuscada));
    return coincideTexto;
  });
  const dir = sortDir.value === 'asc' ? 1 : -1;
  result = [...result].sort((a, b) => {
    let va = '', vb = '';
    if (sortKey.value === 'paciente') { va = nombreCompleto(a).toLowerCase(); vb = nombreCompleto(b).toLowerCase(); }
    else if (sortKey.value === 'clinica') { va = (a.clinica?.nombre ?? '').toLowerCase(); vb = (b.clinica?.nombre ?? '').toLowerCase(); }
    else if (sortKey.value === 'especialidad') { va = (a.especialidad_requerida ?? '').toLowerCase(); vb = (b.especialidad_requerida ?? '').toLowerCase(); }
    else { va = a.created_at; vb = b.created_at; }
    return va < vb ? -dir : va > vb ? dir : 0;
  });
  return result;
});

const totalPaginas = computed(() => Math.max(1, Math.ceil(solicitudesFiltradas.value.length / itemsPorPagina.value)));
const solicitudesPaginadas = computed(() => {
  const start = (paginaActual.value - 1) * itemsPorPagina.value;
  return solicitudesFiltradas.value.slice(start, start + itemsPorPagina.value);
});

/** Páginas numeradas con elipsis: 1 2 … p-1 p p+1 … t-1 t */
const paginasVisibles = computed<(number | string)[]>(() => {
  const t = totalPaginas.value, p = paginaActual.value;
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1);
  const set = new Set([1, 2, p - 1, p, p + 1, t - 1, t].filter(n => n >= 1 && n <= t));
  const arr = [...set].sort((a, b) => a - b);
  const out: (number | string)[] = [];
  let prev = 0;
  for (const n of arr) {
    if (n - prev > 1) out.push('…');
    out.push(n);
    prev = n;
  }
  return out;
});

const paginaSeleccionada = computed(() =>
  solicitudesPaginadas.value.length > 0 && solicitudesPaginadas.value.every(s => seleccionadas.value.has(s.id))
);

function toggleSeleccion(id: number) {
  const s = new Set(seleccionadas.value);
  if (s.has(id)) s.delete(id); else s.add(id);
  seleccionadas.value = s;
}

function toggleSeleccionPagina() {
  const s = new Set(seleccionadas.value);
  if (paginaSeleccionada.value) {
    solicitudesPaginadas.value.forEach(x => s.delete(x.id));
  } else {
    solicitudesPaginadas.value.forEach(x => s.add(x.id));
  }
  seleccionadas.value = s;
}

/** Tiempo real hasta resolución: evento completado/negada, o updated_at como respaldo. */
function tiempoLento(s: Solicitud): boolean {
  return minutosResolucion(s) >= 24 * 60;
}

function minutosResolucion(s: Solicitud): number {
  const ini = new Date(s.created_at).getTime();
  if (isNaN(ini)) return NaN;
  const ev = s.eventos?.find(e => e.tipo === 'completado' || e.tipo === 'negada' || e.tipo === 'negado');
  const fin = ev ? new Date(ev.created_at).getTime() : (s.updated_at ? new Date(s.updated_at).getTime() : NaN);
  if (isNaN(fin) || fin <= ini) return NaN;
  return Math.round((fin - ini) / 60000);
}

function tiempoResolucion(s: Solicitud): string {
  const min = minutosResolucion(s);
  if (isNaN(min)) return '—';
  if (min < 60) return `${min}m`;
  const h = Math.floor(min / 60), m = min % 60;
  if (h < 24) return m ? `${h}h ${m}m` : `${h}h`;
  const d = Math.floor(h / 24), rh = h % 24;
  return rh ? `${d}d ${rh}h` : `${d}d`;
}

async function exportarPdfFila(s: Solicitud) {
  try {
    await exportarSolicitudPdf(s);
  } catch {
    notify.error('No se pudo generar el PDF. Intente de nuevo.');
  }
}

function verHistoria(s: Solicitud) {
  solicitudSeleccionada.value = s;
  abrirHistoriaClinica();
}


function cambiarTab(tab: 'todas' | 'completado' | 'negado' | 'cancelado') {
  tabActiva.value = tab;
  filtroEstado.value = '';
  paginaActual.value = 1;
}

const exportando = ref(false);

async function exportarExcel() {
  const rows = seleccionadas.value.size
    ? solicitudesFiltradas.value.filter(s => seleccionadas.value.has(s.id))
    : solicitudesFiltradas.value;
  try {
    exportando.value = true;
    await exportarSolicitudesExcel(rows.map(s => s.id), 'historico');
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
  return { pendiente: 'Nueva solicitud', pendiente_cama: 'Pendiente', en_espera: 'Aceptado', completado: 'Completado', negado: 'Negado', cancelado: 'Cancelado' }[estado] ?? estado;
}

function grupoNegacionLabel(grupo?: string): string {
  if (grupo === 'no_red') return 'Paciente no RED';
  if (grupo === 'poblacion_objeto') return 'Población objeto';
  return '';
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
  tipo: 'enviada' | 'revision' | 'aceptada' | 'negada' | 'espera' | 'completado';
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
};

/** Notas de seguimiento registradas, de la más reciente a la más antigua. */
const notasSeguimiento = computed(() =>
  (solicitudSeleccionada.value?.eventos ?? [])
    .filter(e => e.tipo === 'nota')
    .slice()
    .sort((a, b) => b.created_at.localeCompare(a.created_at))
);

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
  rangoFechas.value = null;
  filtroClinica.value = '';
  filtroEspecialidad.value = '';
  filtroEps.value = '';
  filtroEstado.value = '';
  filtroOrigen.value = '';
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
    const { data } = await http.get('/api/solicitudes-referencia/historico');
    solicitudes.value = data.data;
    animateCounters([resumen.value.total, resumen.value.completadas, resumen.value.negadas]);
  } catch {
    notify.error('Error al cargar el histórico');
  } finally {
    cargando.value = false;
  }
}

/** Refresco automático de fondo: invisible — no muestra el esqueleto de
 * carga ni anima nada; solo actualiza los datos cuando algo cambió. */
async function cargarSilencioso() {
  try {
    const { data } = await http.get('/api/solicitudes-referencia/historico');
    const cambiados = actualizarSiCambio(solicitudes, data.data);
    if (cambiados.length === 0) return;
    displayStats.value = [resumen.value.total, resumen.value.completadas, resumen.value.negadas];
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
  modalHistoriaClinica.value = false;
  modalDetalle.value = true;
  cargarThumbnails(s);
}

function cerrarDetalle() {
  modalDetalle.value = false;
  limpiarThumbnails();
}

/* ── Cambio manual de estado (también desde el histórico — permite
   revertir una completada/negada a la cola activa) ── */
const authStore = useAuthStore();
const procesando = ref(false);
const modalCambiar = ref(false);
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

const formCambiar = ref({ fecha_respuesta: '', hora_respuesta: '', nombre_quien_responde: '', estado: '', motivo: '', sede: '', codigo_aceptacion: '', hora_llegada: '', lugar_llegada: '', numero_ingreso: '', eps: '', grupo_negacion: '', motivo_negacion: '' });

// "Nueva solicitud" queda fuera del cambio manual — las nuevas solo
// avanzan por el flujo normal (aceptar/negar).
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

function fechaActual(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function horaActual(): string {
  const now = new Date();
  return `${String(now.getHours()).padStart(2, '0')}:${String(Math.floor(now.getMinutes() / 5) * 5).padStart(2, '0')}`;
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
    lugar_llegada: '',
    numero_ingreso: '',
    eps: s.eps || '',
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
      if (['completado', 'negado', 'cancelado'].includes(data.data.estado)) {
        solicitudes.value[idx] = data.data;
      } else {
        // Salió del histórico hacia la cola activa — se quita de esta vista.
        solicitudes.value.splice(idx, 1);
      }
    }
    modalCambiar.value = false;
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'Error al cambiar el estado');
  } finally {
    procesando.value = false;
  }
}

function abrirHistoriaClinica() {
  modalHistoriaClinica.value = true;
}

/** Preselecciona el tab al llegar desde la tarjeta de Completadas/Negadas en Solicitudes (?estado=completado|negado). */
function aplicarEstadoQuery() {
  const estado = route.query.estado;
  if (estado === 'completado' || estado === 'negado' || estado === 'cancelado') {
    tabActiva.value = estado;
    const { estado: _estado, ...resto } = route.query;
    router.replace({ query: resto });
  }
}

onMounted(async () => {
  await cargar();
  aplicarEstadoQuery();
  await aplicarResaltado();
});

usePolling(() => {
  if (!cargando.value && !modalDetalle.value && !modalPdf.value && !modalHistoriaClinica.value) cargarSilencioso();
}, 30000);
</script>

<style scoped>
.ph-solicitudes {
  background: linear-gradient(160deg, #eef4fc 0%, #e3edf8 40%, #f0f5fa 100%);
}

/* ── Stat cards ── */
.sol-stats-bar {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: .75rem;
}
.sol-stats-bar-historico {
  grid-template-columns: repeat(3, 1fr);
}

/* ── Botón Filtros + panel desplegable ── */
.sol-filter-spacer { flex: 1; }
.sol-chips-actions { display: flex; align-items: center; gap: 4px; margin-right: 4px; }
.sol-icon-btn {
  width: 30px; height: 30px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  background: #fff; border: 1px solid #e2e8f0; color: #475569;
  cursor: pointer; transition: all .15s ease;
}
.sol-icon-btn:hover:not(:disabled) { border-color: #bfdbfe; color: var(--rf-primary); background: #eff6ff; }
.sol-icon-btn:disabled { opacity: .45; cursor: not-allowed; }
.dark .sol-icon-btn { background: #1e293b; border-color: #334155; color: #94a3b8; }
.sol-filtros-wrap { position: relative; }
.sol-filtros-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 30px; padding: 0 12px;
  border-radius: 9px; border: 1px solid #d4deea;
  background: rgba(255, 255, 255, 0.7);
  font-size: 11.5px; font-weight: 700; color: #475569;
  cursor: pointer; transition: all .15s ease;
  font-family: inherit;
}
.sol-filtros-btn:hover, .sol-filtros-btn-active {
  border-color: #16468E; color: #16468E; background: #eef4ff;
}
.dark .sol-filtros-btn { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: #cbd5e1; }
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
  width: 230px;
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
.dark .sol-filtros-panel { background: #161b28; border-color: rgba(255, 255, 255, 0.08); }
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

/* ── Transición del panel de filtros ── */
.bulk-slide-enter-active, .bulk-slide-leave-active {
  transition: all .25s ease;
}
.bulk-slide-enter-from, .bulk-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ── Columnas nuevas de la tabla ── */
.sol-tiempo-chip {
  display: inline-block;
  padding: 2px 8px; border-radius: 999px;
  background: #f1f5f9; color: #475569;
  font-size: 10px; font-weight: 700;
}
.dark .sol-tiempo-chip { background: #1e293b; color: #94a3b8; }
.sol-tiempo-chip--lento { background: #fef3c7; color: #b45309; }
.dark .sol-tiempo-chip--lento { background: rgba(245, 158, 11, .15); color: #fbbf24; }

/* ── Paginación numerada ── */
.sol-pagination-num {
  display: flex; align-items: center; justify-content: center;
  min-width: 26px; height: 26px; padding: 0 4px;
  border: 1px solid #e2e8f0; border-radius: 6px;
  background: #fff; color: #64748b;
  font-size: 11px; font-weight: 600;
  cursor: pointer; transition: all .15s ease;
}
.dark .sol-pagination-num { background: #151b26; border-color: #2b3242; color: #94a3b8; }
.sol-pagination-num:hover { border-color: #16468E; color: #16468E; background: #f0f5ff; }
.dark .sol-pagination-num:hover { border-color: #3b82f6; color: #93c5fd; background: rgba(59, 130, 246, .08); }
.sol-pagination-num-active,
.sol-pagination-num-active:hover {
  background: #16468E; border-color: #16468E; color: #fff;
}
.sol-pagination-dots { color: #94a3b8; font-size: 11px; padding: 0 2px; align-self: center; }
.sol-por-pagina { width: 108px; margin-left: .4rem; }

/* ── Header con subtítulo ── */
@media (max-width: 1180px) {
  .sol-stats-bar { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 640px) {
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
.sol-tab-dot--en_espera { background: #3b82f6; }
.sol-tab-dot--completado { background: #22c55e; }
.sol-tab-dot--negado { background: #ef4444; }
.sol-tab-dot--cancelado { background: #94a3b8; }

/* ── Fila resaltada (llegó desde una notificación) ── */
@keyframes sol-row-glow {
  0%, 100% { background: transparent; }
  50% { background: rgba(217, 119, 6, .12); }
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
  padding: .5rem .8rem;
  border-top: 1px solid #f1f5f9;
  background: #fafbfc;
}
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
  border-radius: 6px;
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

/* ── Diagnóstico ── */
.sol-dx-more {
  font-size: 10px;
  font-weight: 700;
  color: #7c3aed;
  padding-left: 2px;
}
/* ── Header ── */
/* ── Filter bar ── */
.sol-filter-bar {
  display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
  padding: .65rem .9rem;
  border-radius: 14px;
  background: linear-gradient(135deg, #f0f6ff 0%, #e6efff 50%, #f0f9ff 100%);
  border: 1px solid #b8c8e0;
  box-shadow: 0 3px 16px rgba(13, 45, 107, 0.07), inset 0 1px 0 rgba(255,255,255,0.6);
  position: relative; overflow: visible;
  z-index: 30;
  transition: box-shadow .3s ease, transform .3s ease;
}
.sol-filter-bar:hover {
  box-shadow: 0 5px 24px rgba(13, 45, 107, 0.11), inset 0 1px 0 rgba(255,255,255,0.6);
  transform: translateY(-1px);
}
.sol-filter-divider {
  width: 1px; height: 24px;
  background: linear-gradient(180deg, transparent, #b8c8e0, transparent);
  flex-shrink: 0;
}

/* ── Tabs ── */
.sol-tabs {
  display: flex; gap: 4px;
  padding: 3px;
  background: rgba(255,255,255,0.7);
  border: 1px solid #d4deea;
  border-radius: 10px;
}
.sol-tab {
  display: flex; align-items: center; gap: 6px;
  padding: 5px 12px;
  border-radius: 8px;
  font-size: 12px; font-weight: 600; color: #64748b;
  background: transparent;
  border: none; cursor: pointer;
  transition: all .2s ease;
}
.sol-tab:hover { color: #1e2d55; background: rgba(13,45,107,.06); transform: translateY(-1px); }
.sol-tab-active {
  background: linear-gradient(135deg, #0D2D6B, #16468E);
  color: #fff;
  box-shadow: 0 2px 10px rgba(13,45,107,.28);
}
.sol-tab-count {
  padding: 1px 6px; border-radius: 999px;
  font-size: 9px; font-weight: 700;
  background: rgba(13,45,107,.08); color: #1e2d55;
}
.sol-tab-active .sol-tab-count { background: rgba(255,255,255,.2); color: #fff; }

/* ── Search ── */
.sol-search { flex: 1; min-width: 200px; max-width: 400px; }
.sol-bar-search { flex: 0 1 280px; min-width: 180px; }
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

/* ── Table panel ── */
.sol-table-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(22,70,142,.08);
  padding: 4px;
}

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

/* ── Tarjetas del histórico ── */
.hist-selectbar {
  display: flex; align-items: center; gap: 10px;
  padding: 5px 12px;
  border-bottom: 1px solid #eef2f9;
}
.hist-selectbar-label {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 10.5px; font-weight: 600; color: #486996;
  cursor: pointer; user-select: none;
}
.hist-selectbar-count {
  font-size: 10px; font-weight: 700; color: #16468E;
  background: #e9f2ff; border-radius: 999px; padding: 2px 8px;
}
.hist-card-scroll { flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 8px 10px 10px; }
.hist-card-list { display: flex; flex-direction: column; gap: 8px; }
.hist-card {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(0, .8fr) minmax(0, 1.2fr) minmax(175px, .7fr) auto;
  grid-template-areas: "identity clinic clinical state actions";
  align-items: center;
  gap: 14px;
  min-height: 80px;
  padding: 8px 12px;
  border: 1px solid rgba(225,235,248,.7);
  border-radius: 9px;
  background: linear-gradient(115deg, #fff, #fbfdff);
  box-shadow: 0 3px 10px rgba(42,88,149,.045);
  transition: border-color .18s ease, box-shadow .18s ease;
}
.hist-card:hover { border-color: #bfd4f5; box-shadow: 0 4px 14px rgba(42,88,149,.1); }
.hist-card:hover .sol-table-status { transform: scale(1.06); }
.hist-card > div { min-width: 0; }
.hist-card--pendiente { border-color: #f5dda6; background: linear-gradient(110deg, #fff6e3, #fffbef); box-shadow: inset 3px 0 0 #f59e0b; }
.hist-card--pendiente_cama { border-color: #f7cd9e; background: linear-gradient(110deg, #fff0dd, #fff8ec); box-shadow: inset 3px 0 0 #f97316; }
.hist-card--en_espera { border-color: #b5d3fb; background: linear-gradient(110deg, #e8f2ff, #f3f9ff); box-shadow: inset 3px 0 0 #3b82f6; }
.hist-card--completado { border-color: #b8e6c8; background: linear-gradient(110deg, #edf9f0, #f7fcf8); box-shadow: inset 3px 0 0 #22c55e; }
.hist-card--negado { border-color: #f3b4bc; background: linear-gradient(110deg, #fff0f2, #fff7f8); box-shadow: inset 3px 0 0 #ef4444; }
.hist-card--cancelado { border-color: #d3dbe6; background: linear-gradient(110deg, #f1f4f8, #f8fafc); box-shadow: inset 3px 0 0 #94a3b8; }
.hist-card-urgent { border-color: #f7829a; background: linear-gradient(110deg, #ffe7ed, #fff1f4); box-shadow: inset 3px 0 0 #e11d48; }
.hist-card-urgent:hover { border-color: #f43f5e; box-shadow: inset 3px 0 0 #be123c, 0 4px 14px rgba(225,29,72,.12); }
.hist-card-resaltada { animation: sol-row-glow 2.2s ease-in-out 2; box-shadow: inset 3px 0 0 #D97706 !important; }
.hist-card-identity { grid-area: identity; display: flex; align-items: center; gap: 9px; }
.hist-card-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  display: grid; place-items: center; flex-shrink: 0;
  font-size: 12px; font-weight: 800;
}
.hist-card-idtext { min-width: 0; flex: 1; }
.hist-card-name {
  display: block; max-width: 100%;
  padding: 0; border: 0; background: none;
  color: #102451; font: inherit; font-size: 13px; font-weight: 750;
  cursor: pointer; text-align: left;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.hist-card-name:hover { color: #2563eb; }
.hist-card-name:focus-visible { outline: 2px solid #60a5fa; outline-offset: 2px; border-radius: 3px; }
.hist-card-name-resaltada {
  text-decoration: underline;
  text-decoration-color: #D97706;
  text-decoration-thickness: 2px;
  text-underline-offset: 3px;
}
.hist-card-doc { margin: 2px 0 0; color: #758db5; font-size: 10.5px; white-space: nowrap; }
.hist-card-block--clinic { grid-area: clinic; }
.hist-card-block--clinical { grid-area: clinical; }
.hist-card-divider { padding-left: 15px; border-left: 1px solid #dfe8f7; }
.hist-card-label { display: block; color: #6c87b0; font-size: 8.5px; font-weight: 600; text-transform: uppercase; line-height: 1.3; letter-spacing: .03em; }
.hist-card-label-gap { margin-top: 5px; }
.hist-card-clinic { margin: 3px 0; color: #18345e; font-size: 12.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hist-card-remitente { margin: 0; color: #728bb3; font-size: 10.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hist-card-specialty { margin: 3px 0 0; color: #075ce8; font-size: 12.5px; font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hist-card-dx { margin: 2px 0 0; color: #34517a; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hist-card-dx-code {
  flex-shrink: 0; font-size: 10px; font-weight: 800; color: #6d28d9;
  background: #ede9fe; padding: 1px 5px; border-radius: 4px; letter-spacing: .02em;
}
.hist-card-state { grid-area: state; display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.hist-card-fecha { margin: 0; color: #506b95; font-size: 10.5px; white-space: nowrap; }
.hist-card-actions { grid-area: actions; display: flex; align-items: center; gap: 5px; }
.hist-card-actions :deep(.el-button) { width: 30px; height: 30px; margin-left: 0 !important; border-color: #dbe5f5; background: #fff; color: #244671; }
.hist-card-actions :deep(.el-button:hover) { color: #2563eb; border-color: #93b9f5; background: #f0f6ff; }
.dark .hist-selectbar { border-bottom-color: rgba(255,255,255,.07); }
.dark .hist-selectbar-label { color: #94a3b8; }
.dark .hist-selectbar-count { background: rgba(96,165,250,.16); color: #93c5fd; }
.dark .hist-card { background: linear-gradient(115deg, #192439, #162133); border-color: #293b56; box-shadow: 0 3px 10px rgba(0,0,0,.12); }
.dark .hist-card:hover { border-color: #4771ab; }
.dark .hist-card--pendiente { border-color: #6b5526; background: linear-gradient(110deg, #33290f, #28220f); box-shadow: inset 3px 0 0 #d9a028; }
.dark .hist-card--pendiente_cama { border-color: #7a4d21; background: linear-gradient(110deg, #35240f, #2a1f10); box-shadow: inset 3px 0 0 #f07d22; }
.dark .hist-card--en_espera { border-color: #2e5a9c; background: linear-gradient(110deg, #16263f, #182135); box-shadow: inset 3px 0 0 #4d8ef7; }
.dark .hist-card--completado { border-color: #2f6b44; background: linear-gradient(110deg, #14291c, #16241b); box-shadow: inset 3px 0 0 #34c468; }
.dark .hist-card--negado { border-color: #7c3a44; background: linear-gradient(110deg, #331e24, #2a1d22); box-shadow: inset 3px 0 0 #f05656; }
.dark .hist-card--cancelado { border-color: #3a4558; background: linear-gradient(110deg, #1d2432, #1a202c); box-shadow: inset 3px 0 0 #7c8aa0; }
.dark .hist-card-urgent { border-color: #b03d5c; background: linear-gradient(110deg, #3d1f2e, #2c1d2b); box-shadow: inset 3px 0 0 #fb4d78; }
.dark .hist-card-name { color: #dbe7fb; }
.dark .hist-card-name:hover { color: #93c5fd; }
.dark .hist-card-doc { color: #6d84a8; }
.dark .hist-card-divider { border-left-color: #314666; }
.dark .hist-card-label { color: #7d95bb; }
.dark .hist-card-clinic { color: #d3e1f5; }
.dark .hist-card-remitente { color: #8ba3c9; }
.dark .hist-card-specialty { color: #7db0f8; }
.dark .hist-card-dx { color: #9db4d6; }
.dark .hist-card-fecha { color: #8ba3c9; }
.dark .hist-card-actions :deep(.el-button) { border-color: #314666; background: #1c2d46; color: #bcd1ef; }
.dark .hist-card-actions :deep(.el-button:hover) { border-color: #4771ab; background: #263d5d; color: #93c5fd; }

@media (max-width: 1100px) {
  .hist-card { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) auto; grid-template-areas: "identity clinic actions" "clinical state state"; gap: 10px 14px; }
}
@media (max-width: 640px) {
  .hist-card { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "identity actions" "clinic clinic" "clinical clinical" "state state"; gap: 8px; padding: 10px; }
  .hist-card-divider { padding-left: 0; border-left: 0; }
  .hist-card-state { flex-direction: row; flex-wrap: wrap; align-items: center; }
}

/* ── Estado chip ── */
.sol-table-status {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 999px;
  font-size: 10px; font-weight: 700;
  transition: transform .2s ease;
}
.sol-status-dot { width: 5px; height: 5px; border-radius: 50%; }
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
.sol-status-cancelled { background: #e2e8f0; color: #475569; }
.sol-status-cancelled .sol-status-dot { background: #64748b; }
.dark .sol-status-cancelled { background: #2a3447; color: #b6c2d6; }

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
  gap: .65rem;
  padding: .7rem 3.5rem .7rem 1.1rem;
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
.detalle-head-badge.estado-pendiente_cama { background: #ffedd5; color: #c2410c; }
.detalle-head-badge.estado-en_espera { background: #dbeafe; color: #1d4ed8; }
.detalle-head-badge.estado-completado { background: #dcfce7; color: #16a34a; }
.detalle-head-badge.estado-negado { background: #fee2e2; color: #b91c1c; }
.detalle-head-badge.estado-cancelado { background: #e2e8f0; color: #475569; }

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
  padding: .7rem 1.1rem;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
}
.detalle-body::-webkit-scrollbar { width: 5px; }
.detalle-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

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
  gap: .4rem;
  padding: .45rem .6rem .25rem;
}
.detalle-card-icon {
  width: 1.6rem; height: 1.6rem;
  border-radius: 8px;
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

.card-body { padding: 0 .6rem .45rem; }
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

.card-dx-list { display: flex; flex-direction: column; gap: 5px; }
.card-dx-item {
  display: flex; align-items: baseline; gap: 6px;
  padding: 5px 8px; border-radius: 8px;
  background: #f5f3ff;
}
.card-dx-code {
  font-size: 11px; font-weight: 800; color: #7c3aed;
  font-family: monospace; flex-shrink: 0;
}
.card-dx-desc { font-size: 11px; color: #334155; }

.data-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: .4rem;
  padding: .12rem 0;
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
  background: linear-gradient(135deg, #4F46E5, #7C3AED);
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(124, 58, 237, .3);
  transition: all .2s ease;
  flex-shrink: 0;
}
.detalle-cerrar-btn:hover {
  background: linear-gradient(135deg, #5B52F0, #8B47E8);
  box-shadow: 0 6px 20px rgba(124, 58, 237, .4);
}

/* ── Modal de acción "Cambiar estado" (mismas clases que en Solicitudes) ── */
:deep(.accion-dialog) {
  border-radius: 22px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(11, 35, 73, .4), 0 0 0 1px rgba(255,255,255,.08);
}
:deep(.accion-dialog .el-dialog__header) { display: none; }
:deep(.accion-dialog .el-dialog__body) { padding: 0; overflow: hidden; }
:deep(.accion-dialog .el-input__wrapper),
:deep(.accion-dialog .el-select__wrapper),
:deep(.accion-dialog .el-textarea__inner) {
  border-radius: 10px;
  box-shadow: 0 0 0 1px #e2e8f0 inset;
  background: #fff;
  transition: box-shadow .15s ease;
}
:deep(.accion-dialog .el-input__wrapper),
:deep(.accion-dialog .el-select__wrapper) { min-height: 33px; }
:deep(.accion-dialog .el-input__wrapper:hover),
:deep(.accion-dialog .el-select__wrapper:hover) { box-shadow: 0 0 0 1px #cbd5e1 inset; }
:deep(.accion-dialog .el-input__wrapper.is-focus),
:deep(.accion-dialog .el-select__wrapper.is-focused),
:deep(.accion-dialog .el-textarea__inner:focus) { box-shadow: 0 0 0 2px rgba(79,70,229,.35) inset; }
:deep(.accion-dialog .el-input__wrapper.is-disabled),
:deep(.accion-dialog .el-select__wrapper.is-disabled) { background: #f8fafc; }

.accion-icon-violet { background: linear-gradient(135deg, #818cf8, #6366f1) !important; box-shadow: 0 6px 16px rgba(99,102,241,.3) !important; }
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
.accion-cancelar-btn {
  padding: .55rem 1.15rem;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
}
.accion-cancelar-btn:hover { border-color: #cbd5e1; color: #334155; }
.accion-confirmar-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .55rem 1.15rem;
  border-radius: 10px;
  border: none;
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
}
.accion-confirmar-btn:disabled { opacity: .65; cursor: default; }
.accion-confirmar-violet { background: linear-gradient(135deg, #818cf8, #6366f1); box-shadow: 0 4px 14px rgba(99,102,241,.3); }
.accion-confirmar-violet:hover:not(:disabled) { background: linear-gradient(135deg, #a5b4fc, #818cf8); box-shadow: 0 6px 20px rgba(99,102,241,.4); }

/* Notas de seguimiento */
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
</style>
