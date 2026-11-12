<template>
  <div class="historial-page h-full overflow-y-auto p-4 sm:p-5 animate-fade-in-up"
    style="animation-duration: 0.4s; animation-fill-mode: both;">

    <!-- ── Header ── -->
    <div class="historial-header flex items-center justify-between mb-4 shrink-0 anim-fade-down">
      <div class="flex items-center gap-2.5">
        <span class="historial-header-bar"></span>
        <div>
          <h1 class="historial-header-title text-lg sm:text-xl font-bold">Historial de solicitudes</h1>
          <p class="historial-header-sub text-xs mt-1">Consulta y gestiona todas las remisiones enviadas.</p>
        </div>
      </div>
      <button @click="cargar" class="historial-refresh inline-flex items-center gap-1.5 text-xs font-medium transition-colors">
        <component :is="RefreshCwIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': cargando }" />
        Actualizar
      </button>
    </div>

    <!-- ── Resumen rápido ── -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
      <StatCard
        variant="pastel" :dark="isDark" tone="info"
        label="Total" :value="resumen.total" comparacion="Solicitudes registradas"
        :icon="FileTextIcon" :sparkline="sparklineTotal" class="anim-slide-up" style="animation-delay:0.05s"
      />
      <StatCard
        variant="pastel" :dark="isDark" tone="warning"
        label="Pendientes" :value="resumen.pendientes" comparacion="Sin revisar"
        :icon="ClockIcon" :sparkline="sparklinePendientes" class="anim-slide-up" style="animation-delay:0.1s"
      />
      <StatCard
        variant="pastel" :dark="isDark" tone="info"
        label="En espera" :value="resumen.enEspera" comparacion="Esperando llegada"
        :icon="HourglassIcon" :sparkline="sparklineEnEspera" class="anim-slide-up" style="animation-delay:0.15s"
      />
      <StatCard
        variant="pastel" :dark="isDark" tone="violet"
        label="Completadas" :value="resumen.completadas" comparacion="Pacientes atendidos"
        :icon="CheckCircleIcon" :sparkline="sparklineCompletadas" class="anim-slide-up" style="animation-delay:0.2s"
      />
      <StatCard
        variant="pastel" :dark="isDark" tone="danger"
        label="Negadas" :value="resumen.negadas" comparacion="Remisiones rechazadas"
        :icon="XCircleIcon" :sparkline="sparklineNegadas" class="anim-slide-up" style="animation-delay:0.3s"
      />
    </div>

    <!-- ── Filtros ── -->
    <div class="filtros-card rounded-2xl p-3 sm:p-4 mb-4 flex items-end gap-3 flex-wrap" style="overflow:hidden;">
      <div class="flex-1 min-w-[160px]">
        <label class="filter-label">Buscar</label>
        <el-input v-model="busqueda" placeholder="Buscar por paciente, documento o EPS…" :prefix-icon="SearchIcon" clearable size="default" />
      </div>
      <div class="shrink-0">
        <label class="filter-label">Estado</label>
        <select v-model="filtroEstado" class="filter-select">
          <option value="">Todos</option>
          <option value="pendiente">Pendientes</option>
          <option value="en_espera">En espera</option>
          <option value="completado">Completadas</option>
          <option value="negado">Negadas</option>
        </select>
      </div>
      <div class="shrink-0">
        <label class="filter-label">Especialidad</label>
        <select v-model="filtroEspecialidad" class="filter-select">
          <option value="">Todas</option>
          <option v-for="e in ESPECIALIDADES" :key="e" :value="e">{{ e }}</option>
        </select>
      </div>
      <div class="shrink-0">
        <label class="filter-label">EPS</label>
        <select v-model="filtroEps" class="filter-select">
          <option value="">Todas</option>
          <option v-for="e in EPS_LIST_EXTERNA" :key="e" :value="e">{{ e }}</option>
        </select>
      </div>
      <div class="shrink-0">
        <label class="filter-label">Fecha</label>
        <el-date-picker
          v-model="rangoFecha"
          type="daterange"
          unlink-panels
          range-separator="–"
          start-placeholder="Desde"
          end-placeholder="Hasta"
          size="default"
          format="DD/MM/YYYY"
          value-format="YYYY-MM-DD"
          class="filter-daterange"
        />
      </div>
    </div>

    <!-- ── Tabla ── -->
    <div v-if="cargando" class="space-y-2">
      <div v-for="i in 5" :key="i" class="skeleton-row rounded-xl px-4 py-3 flex items-center gap-3">
        <div class="skeleton-circle"></div>
        <div class="flex-1 space-y-1.5">
          <div class="skeleton-bar" style="width:30%; height:10px;"></div>
          <div class="skeleton-bar" style="width:50%; height:8px;"></div>
        </div>
        <div class="skeleton-bar" style="width:60px; height:18px; border-radius:999px;"></div>
      </div>
    </div>

    <div v-else-if="solicitudesFiltradas.length === 0" class="empty-state-wrap">
      <div class="empty-state-glow"></div>
      <div class="empty-state-icon w-16 h-16 rounded-2xl flex items-center justify-center mb-4 relative z-10">
        <component :is="ClipboardListIcon" class="w-8 h-8" />
      </div>
      <p class="font-bold text-base mb-1.5 relative z-10" style="color:#0d2d5e;">No se encontraron solicitudes</p>
      <p class="text-xs max-w-[300px] mb-5 relative z-10" style="color:#64748b;">Ajuste los filtros de búsqueda o cree una nueva solicitud desde el dashboard para empezar a gestionar sus remisiones.</p>
      <button class="empty-state-btn relative z-10" @click="limpiarFiltros">
        <component :is="RefreshCwIcon" class="w-3.5 h-3.5" />
        <span>Limpiar filtros</span>
      </button>
    </div>

    <div v-else>
      <!-- Tabla desktop -->
      <div class="tabla-card rounded-2xl overflow-hidden hidden sm:block">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-[10px] font-bold uppercase tracking-wider" :style="{ color: isDark ? '#64748b' : '#8a9ab5', background: isDark ? '#1e293b' : '#f8fafc' }">
              <th class="px-4 py-3">Paciente</th>
              <th class="px-4 py-3 hidden sm:table-cell">Especialidad</th>
              <th class="px-4 py-3 hidden md:table-cell">EPS</th>
              <th class="px-4 py-3 hidden lg:table-cell">Fecha</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(sol, idx) in solicitudesPaginadas"
              :id="`hist-row-${sol.id}`"
              :key="sol.id"
              class="table-row cursor-pointer transition-all anim-row-in"
              :class="{ 'table-row-resaltada': resaltarId === sol.id }"
              :style="{ animationDelay: (idx * 0.04) + 's' }"
              @click="verDetalle(sol)"
            >
              <td class="px-4 py-3">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 text-[10px] font-bold"
                    :style="{ background: sol.estado === 'pendiente' ? '#fef3c7' : sol.estado === 'en_espera' ? '#dbeafe' : sol.estado === 'completado' ? '#dcfce7' : '#fee2e2', color: sol.estado === 'pendiente' ? '#d97706' : sol.estado === 'en_espera' ? '#2563eb' : sol.estado === 'completado' ? '#16a34a' : '#dc2626' }">
                    {{ initialesPaciente(sol) }}
                  </div>
                  <div class="min-w-0">
                    <p class="font-bold text-xs truncate" :style="{ color: isDark ? '#e2e8f0' : '#1e2d55' }">{{ sol.primer_nombre }} {{ sol.primer_apellido }}</p>
                    <p class="text-[10px] truncate" :style="{ color: isDark ? '#94a3b8' : '#8a9ab5' }">{{ sol.tipo_documento }} {{ sol.numero_documento }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 hidden sm:table-cell text-xs" :style="{ color: isDark ? '#cbd5e1' : '#475569' }">{{ sol.especialidad_requerida }}</td>
              <td class="px-4 py-3 hidden md:table-cell">
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md" :style="{ background: isDark ? '#1e293b' : '#f1f5f9', color: isDark ? '#cbd5e1' : '#475569' }">{{ epsBase(sol.eps) }}</span>
              </td>
              <td class="px-4 py-3 hidden lg:table-cell">
                <div class="fecha-cell">
                  <component :is="CalendarIcon" class="w-3 h-3 shrink-0" :style="{ color: isDark ? '#64748b' : '#b0bccf' }" />
                  <div>
                    <p class="fecha-cell-dia">{{ formatFecha(sol.created_at) }}</p>
                    <p v-if="sol.hora" class="fecha-cell-hora">{{ formatHora(sol.hora) }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="estado-badge text-[10px] font-bold px-2.5 py-1 rounded-full"
                  :class="'estado-' + sol.estado"
                >
                  {{ estadoLabel(sol.estado) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button type="button" class="accion-btn" @click.stop="verDetalle(sol)" title="Ver detalle">
                  <component :is="EyeIcon" class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Paginación desktop -->
        <div v-if="totalPaginas > 1" class="flex items-center justify-center gap-1.5 px-4 py-3 border-t" style="border-color:#edf1f7;">
          <button @click="pagina = Math.max(1, pagina - 1)" :disabled="pagina === 1" class="pag-arrow-btn">
            <component :is="ChevronLeftIcon" class="w-4 h-4" />
          </button>
          <template v-for="(p, i) in paginasVisibles" :key="i">
            <span v-if="p === '...'" class="pag-ellipsis">…</span>
            <button v-else @click="pagina = p" class="pag-num-btn" :class="{ 'pag-num-btn-active': p === pagina }">{{ p }}</button>
          </template>
          <button @click="pagina = Math.min(totalPaginas, pagina + 1)" :disabled="pagina === totalPaginas" class="pag-arrow-btn">
            <component :is="ChevronRightIcon" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Cards móvil -->
      <div class="sm:hidden space-y-2.5">
        <div
          v-for="(sol, idx) in solicitudesPaginadas"
          :id="`hist-row-m-${sol.id}`"
          :key="sol.id"
          class="mobile-card rounded-xl p-3 cursor-pointer anim-row-in"
          :class="{ 'table-row-resaltada': resaltarId === sol.id }"
          :style="{ animationDelay: (idx * 0.04) + 's' }"
          @click="verDetalle(sol)"
        >
          <div class="flex items-center gap-2.5 mb-2">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 text-[10px] font-bold"
              :style="{ background: sol.estado === 'pendiente' ? '#fef3c7' : sol.estado === 'en_espera' ? '#dbeafe' : sol.estado === 'completado' ? '#dcfce7' : '#fee2e2', color: sol.estado === 'pendiente' ? '#d97706' : sol.estado === 'en_espera' ? '#2563eb' : sol.estado === 'completado' ? '#16a34a' : '#dc2626' }">
              {{ initialesPaciente(sol) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-bold text-xs truncate" style="color:#1e2d55;">{{ sol.primer_nombre }} {{ sol.primer_apellido }}</p>
              <p class="text-[10px] truncate" style="color:#8a9ab5;">{{ sol.tipo_documento }} {{ sol.numero_documento }}</p>
            </div>
            <span class="estado-badge text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0"
              :class="'estado-' + sol.estado"
            >
              {{ estadoLabel(sol.estado) }}
            </span>
          </div>
          <div class="flex items-center gap-3 text-[10px]" :style="{ color: isDark ? '#94a3b8' : '#64748b' }">
            <span>{{ sol.especialidad_requerida }}</span>
            <span class="font-semibold px-1.5 py-0.5 rounded" :style="{ background: isDark ? '#1e293b' : '#f1f5f9' }">{{ epsBase(sol.eps) }}</span>
            <span class="ml-auto">{{ formatFecha(sol.created_at) }}</span>
          </div>
        </div>

        <!-- Paginación móvil -->
        <div v-if="totalPaginas > 1" class="flex items-center justify-between pt-2">
          <button @click="pagina = Math.max(1, pagina - 1)" :disabled="pagina === 1" class="pag-btn">Anterior</button>
          <span class="text-[10px] font-bold" style="color:#16468e;">{{ pagina }} / {{ totalPaginas }}</span>
          <button @click="pagina = Math.min(totalPaginas, pagina + 1)" :disabled="pagina === totalPaginas" class="pag-btn">Siguiente</button>
        </div>
      </div>
    </div>

    <!-- ── Modal: Detalle ──────────────────────────────────────────────── -->
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
              <p class="detalle-head-sub">ID #{{ solicitudSeleccionada.id }} · {{ formatFecha(solicitudSeleccionada.created_at) }} · {{ formatHora(solicitudSeleccionada.hora) }}</p>
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
                  <div class="data-row"><span>Nombre</span><strong>{{ solicitudSeleccionada.primer_nombre }} {{ solicitudSeleccionada.segundo_nombre }} {{ solicitudSeleccionada.primer_apellido }} {{ solicitudSeleccionada.segundo_apellido }}</strong></div>
                  <div class="data-row"><span>Documento</span><strong>{{ solicitudSeleccionada.tipo_documento }} {{ solicitudSeleccionada.numero_documento }}</strong></div>
                  <div class="data-row"><span>Edad / Género</span><strong>{{ solicitudSeleccionada.edad }} años · {{ solicitudSeleccionada.genero === 'M' ? 'Masc.' : 'Fem.' }}</strong></div>
                  <div class="data-row"><span>EPS</span><strong>{{ epsBase(solicitudSeleccionada.eps) }}</strong></div>
                </div>
              </div>
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-amber"><component :is="BuildingIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-amber">Remisión</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Especialidad</span><strong>{{ solicitudSeleccionada.especialidad_requerida }}</strong></div>
                  <div class="data-row"><span>Servicio actual</span><strong>{{ solicitudSeleccionada.servicio_ubicacion_actual }}</strong></div>
                  <div class="data-row"><span>Municipio</span><strong>{{ solicitudSeleccionada.municipio_capita }}</strong></div>
                  <div v-if="solicitudSeleccionada.servicio_remision" class="data-row"><span>Destino</span><strong>{{ solicitudSeleccionada.servicio_remision }}</strong></div>
                  <div v-if="solicitudSeleccionada.quien_remitente" class="data-row"><span>Remite</span><strong>{{ solicitudSeleccionada.quien_remitente }}</strong></div>
                  <div v-if="solicitudSeleccionada.telefono_contacto" class="data-row"><span>Teléfono</span><strong>{{ solicitudSeleccionada.telefono_contacto }}</strong></div>
                  <div v-if="solicitudSeleccionada.correo_contacto" class="data-row"><span>Correo</span><strong>{{ solicitudSeleccionada.correo_contacto }}</strong></div>
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
                      <span class="card-dx-desc">{{ dx.descripcion }}</span>
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
                  @click="modalHistoria = true"
                >Leer más</button>
              </div>
            </div>

            <!-- Código de aceptación + Respuesta/Negación en fila compacta -->
            <div v-if="solicitudSeleccionada.codigo_aceptacion || solicitudSeleccionada.observaciones_respuesta || solicitudSeleccionada.motivo_negacion" class="flex gap-2 mb-1.5 flex-wrap">
              <div v-if="solicitudSeleccionada.codigo_aceptacion" class="detalle-code-bar" style="margin-bottom:0; flex:1;">
                <component :is="CheckCircleIcon" class="w-4 h-4 text-emerald-600" />
                <span class="text-xs text-emerald-700 font-semibold">Código</span>
                <span class="detalle-code-value">{{ solicitudSeleccionada.codigo_aceptacion }}</span>
              </div>
              <div v-if="solicitudSeleccionada.observaciones_respuesta" class="detalle-respuesta" style="margin-bottom:0; flex:1;">
                <div class="flex items-center gap-2 mb-0.5">
                  <component :is="CheckCircleIcon" class="w-3.5 h-3.5 text-blue-600" />
                  <p class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider">Observaciones</p>
                </div>
                <p class="text-[11px] text-blue-600">{{ solicitudSeleccionada.observaciones_respuesta }}</p>
              </div>
              <div v-if="solicitudSeleccionada.motivo_negacion" class="detalle-negacion" style="margin-bottom:0; flex:1;">
                <div class="flex items-center gap-2 mb-0.5">
                  <component :is="XCircleIcon" class="w-3.5 h-3.5 text-red-500" />
                  <p class="text-[10px] font-extrabold text-red-500 uppercase tracking-wider">Negación</p>
                </div>
                <p class="text-[11px] text-slate-700">{{ solicitudSeleccionada.motivo_negacion }}</p>
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
                </div>
              </div>
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-rose"><component :is="PaperclipIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-rose">Soportes</p>
                </div>
                <div class="card-body">
                  <div v-if="solicitudSeleccionada.adjuntos?.length" class="adjunto-list">
                    <div v-for="adj in solicitudSeleccionada.adjuntos" :key="adj.id" class="adjunto-row">
                      <img v-if="thumbnails.has(adj.id)" :src="thumbnails.get(adj.id)" class="adjunto-thumb" :alt="adj.nombre_original" />
                      <span v-else class="adjunto-icon" :class="adj.mime_type?.includes('pdf') ? 'adjunto-pdf' : 'adjunto-img'">{{ adj.mime_type?.includes('pdf') ? 'PDF' : esImagenMime(adj.mime_type) ? 'IMG' : 'DOC' }}</span>
                      <div class="adjunto-info">
                        <p class="adjunto-name">{{ adj.nombre_original }}</p>
                        <p class="adjunto-meta">{{ (adj.mime_type?.includes('pdf') ? 'PDF' : esImagenMime(adj.mime_type) ? 'Imagen' : 'Documento') }} · {{ (adj.tamano / 1024 / 1024).toFixed(1) }} MB</p>
                      </div>
                      <button type="button" class="adjunto-descargar-btn" :disabled="descargando === adj.id" @click="descargarAdjunto(solicitudSeleccionada, adj)" title="Descargar">
                        <component :is="descargando === adj.id ? Loader2Icon : DownloadIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': descargando === adj.id }" />
                      </button>
                    </div>
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

    <!-- ── Modal: Historia clínica completa ──────────────────────────── -->
    <el-dialog v-model="modalHistoria" width="560px" class="historia-dialog" :show-close="true" align-center title="Historia clínica completa">
      <p class="historia-full-text">{{ solicitudSeleccionada?.resumen_historia_clinica }}</p>
    </el-dialog>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePolling } from '@/lib/usePolling';
import { actualizarSiCambio } from '@/lib/silentRefresh';
import notify from '@/plugins/toast';
import { exportarSolicitudPdf } from '@/lib/exportarSolicitudPdf';
import {
  Search as SearchIcon,
  Clock as ClockIcon,
  CheckCircle as CheckCircleIcon,
  XCircle as XCircleIcon,
  Eye as EyeIcon,
  Calendar as CalendarIcon,
  ChevronLeft as ChevronLeftIcon,
  ChevronRight as ChevronRightIcon,
  ClipboardList as ClipboardListIcon,
  FileText as FileTextIcon,
  RefreshCw as RefreshCwIcon,
  FileDown as FileDownIcon,
  X as XIcon,
  Send as SendIcon,
  Shield as ShieldIcon,
  Download as DownloadIcon,
  User as UserIcon,
  Building2 as BuildingIcon,
  Paperclip as PaperclipIcon,
  Hourglass as HourglassIcon,
  Loader2 as Loader2Icon,
} from '@lucide/vue';
import http from '@/plugins/axios';
import { ESPECIALIDADES, EPS_LIST_EXTERNA, epsBase } from '@/data/referencia';
import StatCard from '@/components/ui/StatCard.vue';
import { useClinicaLayoutStore } from '@/stores/clinicaLayout';

const layout = useClinicaLayoutStore();
const isDark = computed(() => layout.isDarkMode);

const route = useRoute();
const router = useRouter();
const solicitudes = ref<any[]>([]);
const resaltarId = ref<number | null>(null);
const cargando = ref(false);
const modalDetalle = ref(false);
const modalHistoria = ref(false);
const solicitudSeleccionada = ref<any>(null);
const thumbnails = ref<Map<number, string>>(new Map());
const descargando = ref<number | null>(null);

const busqueda = ref('');
const filtroEstado = ref('');
const filtroEspecialidad = ref('');
const filtroEps = ref('');
const filtroDesde = ref('');
const filtroHasta = ref('');
const rangoFecha = computed({
  get: (): [string, string] | null => (filtroDesde.value && filtroHasta.value) ? [filtroDesde.value, filtroHasta.value] : null,
  set: (val: [string, string] | null) => {
    filtroDesde.value = val?.[0] ?? '';
    filtroHasta.value = val?.[1] ?? '';
  },
});
const pagina = ref(1);
const porPagina = 12;

const resumen = computed(() => ({
  pendientes: solicitudes.value.filter(s => s.estado === 'pendiente').length,
  enEspera: solicitudes.value.filter(s => s.estado === 'en_espera').length,
  completadas: solicitudes.value.filter(s => s.estado === 'completado').length,
  negadas: solicitudes.value.filter(s => s.estado === 'negado').length,
  total: solicitudes.value.length,
}));

/** Tendencia real: conteo acumulado por corte de tiempo desde la primera solicitud, para cada categoría de estado. */
function sparklineAcumulado(filtro: (s: any) => boolean): number[] {
  const fechas = solicitudes.value
    .filter(filtro)
    .map(s => s.created_at?.slice(0, 10))
    .filter((f): f is string => !!f)
    .sort();
  if (fechas.length < 2) return [];

  const desde = new Date(fechas[0]);
  const hasta = new Date();
  const dias = Math.max(1, Math.round((hasta.getTime() - desde.getTime()) / (1000 * 60 * 60 * 24)));
  const puntos = Math.min(14, dias + 1);

  return Array.from({ length: puntos }, (_, i) => {
    const corte = new Date(desde.getTime() + (dias * i) / (puntos - 1 || 1) * 24 * 60 * 60 * 1000);
    return fechas.filter(f => new Date(f) <= corte).length;
  });
}

const sparklinePendientes = computed(() => sparklineAcumulado(s => s.estado === 'pendiente'));
const sparklineEnEspera = computed(() => sparklineAcumulado(s => s.estado === 'en_espera'));
const sparklineCompletadas = computed(() => sparklineAcumulado(s => s.estado === 'completado'));
const sparklineNegadas = computed(() => sparklineAcumulado(s => s.estado === 'negado'));
const sparklineTotal = computed(() => sparklineAcumulado(() => true));

const solicitudesFiltradas = computed(() => {
  let lista = solicitudes.value;

  if (busqueda.value) {
    const q = busqueda.value.toLowerCase();
    lista = lista.filter(s =>
      `${s.primer_nombre} ${s.primer_apellido}`.toLowerCase().includes(q) ||
      `${s.tipo_documento} ${s.numero_documento}`.toLowerCase().includes(q) ||
      s.eps?.toLowerCase().includes(q),
    );
  }

  if (filtroEstado.value) {
    lista = lista.filter(s => s.estado === filtroEstado.value);
  }

  if (filtroEspecialidad.value) {
    lista = lista.filter(s => s.especialidad_requerida === filtroEspecialidad.value);
  }

  if (filtroEps.value) {
    lista = lista.filter(s => epsBase(s.eps) === filtroEps.value);
  }

  if (filtroDesde.value) {
    lista = lista.filter(s => s.created_at?.slice(0, 10) >= filtroDesde.value);
  }

  if (filtroHasta.value) {
    lista = lista.filter(s => s.created_at?.slice(0, 10) <= filtroHasta.value);
  }

  return lista;
});

const totalPaginas = computed(() => Math.max(1, Math.ceil(solicitudesFiltradas.value.length / porPagina)));

const solicitudesPaginadas = computed(() => {
  const start = (pagina.value - 1) * porPagina;
  return solicitudesFiltradas.value.slice(start, start + porPagina);
});

/** Números de página a mostrar, con "…" cuando hay muchas (ej: 1 2 3 … 5). */
const paginasVisibles = computed<(number | '...')[]>(() => {
  const total = totalPaginas.value;
  const actual = pagina.value;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

  const set = new Set([1, 2, total, total - 1, actual - 1, actual, actual + 1]);
  const nums = [...set].filter(n => n >= 1 && n <= total).sort((a, b) => a - b);

  const resultado: (number | '...')[] = [];
  nums.forEach((n, i) => {
    if (i > 0 && n - (nums[i - 1] as number) > 1) resultado.push('...');
    resultado.push(n);
  });
  return resultado;
});

async function cargar() {
  cargando.value = true;
  try {
    const { data } = await http.get('/api/externo/solicitudes');
    solicitudes.value = data.data;
  } catch {
    notify.error('Error al cargar el historial');
  } finally {
    cargando.value = false;
  }
}

/** Refresco automático de fondo: sin esqueleto de carga, y solo toca lo que
 * de verdad cambió, resaltando esas filas puntuales. */
async function cargarSilencioso() {
  try {
    const { data } = await http.get('/api/externo/solicitudes');
    actualizarSiCambio(solicitudes, data.data);
  } catch {
    // Refresco de fondo: si falla, se reintenta en el siguiente ciclo sin interrumpir al usuario.
  }
}

function adjuntoUrl(sol: any, adj: any): string {
  return `/api/externo/solicitudes/${sol.id}/adjuntos/${adj.id}/descargar`;
}

function esImagenMime(mime?: string): boolean {
  return !!mime && mime.startsWith('image/');
}

function limpiarThumbnails() {
  thumbnails.value.forEach(url => URL.revokeObjectURL(url));
  thumbnails.value = new Map();
}

async function cargarThumbnails(sol: any) {
  const imagenes = (sol.adjuntos ?? []).filter((a: any) => esImagenMime(a.mime_type));
  for (const adj of imagenes) {
    try {
      const { data } = await http.get(adjuntoUrl(sol, adj), { responseType: 'blob' });
      thumbnails.value.set(adj.id, URL.createObjectURL(data));
      thumbnails.value = new Map(thumbnails.value);
    } catch {
      // Si falla la miniatura, se muestra solo la insignia de tipo — no es crítico.
    }
  }
}

async function descargarAdjunto(sol: any, adj: any) {
  descargando.value = adj.id;
  try {
    const { data } = await http.get(adjuntoUrl(sol, adj), { responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = adj.nombre_original;
    enlace.click();
    URL.revokeObjectURL(url);
  } catch {
    notify.error(`No se pudo descargar "${adj.nombre_original}". Intente de nuevo.`);
  } finally {
    descargando.value = null;
  }
}

/** Resalta y hace scroll hasta la solicitud referenciada desde "Últimas referencias" (?resaltar=ID). */
async function aplicarResaltado() {
  const id = Number(route.query.resaltar);
  if (!id) return;

  busqueda.value = '';
  filtroEstado.value = '';
  filtroEspecialidad.value = '';
  filtroEps.value = '';
  filtroDesde.value = '';
  filtroHasta.value = '';

  resaltarId.value = id;
  const indice = solicitudesFiltradas.value.findIndex(s => s.id === id);
  if (indice >= 0) pagina.value = Math.floor(indice / porPagina) + 1;

  await nextTick();
  document.getElementById(`hist-row-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  document.getElementById(`hist-row-m-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });

  const { resaltar: _resaltar, ...resto } = route.query;
  router.replace({ query: resto });
}

function verDetalle(sol: any) {
  limpiarThumbnails();
  solicitudSeleccionada.value = sol;
  modalDetalle.value = true;
  cargarThumbnails(sol);
}

function cerrarDetalle() {
  modalDetalle.value = false;
  limpiarThumbnails();
}

function limpiarFiltros() {
  busqueda.value = '';
  filtroEstado.value = '';
  filtroEspecialidad.value = '';
  filtroEps.value = '';
  filtroDesde.value = '';
  filtroHasta.value = '';
  pagina.value = 1;
  notify.info('Filtros limpiados');
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

function initialesPaciente(solicitud: any): string {
  return `${solicitud.primer_nombre?.[0] ?? ''}${solicitud.primer_apellido?.[0] ?? ''}`.toUpperCase();
}

function estadoLabel(estado: string): string {
  return { pendiente: 'Pendiente', en_espera: 'En espera', completado: 'Completada', negado: 'Negada' }[estado] ?? estado;
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
    fecha: `${formatFecha(s.created_at)} · ${formatHora(s.hora)}`,
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

  pasos.push({ titulo: 'Solicitud aceptada', fecha: respuestaFecha, hecho: true, tipo: 'aceptada' });

  const eventoEspera = s.eventos?.find((e: any) => e.tipo === 'en_espera');
  if (eventoEspera || s.estado === 'en_espera' || s.estado === 'completado') {
    pasos.push({
      titulo: 'Paciente en espera',
      fecha: eventoEspera ? `${formatFecha(eventoEspera.created_at)} · ${horaDeFecha(eventoEspera.created_at)}` : '—',
      hecho: !!eventoEspera,
      tipo: 'espera',
    });
  }

  const eventoCompletado = s.eventos?.find((e: any) => e.tipo === 'completado');
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

async function exportarPdf() {
  const s = solicitudSeleccionada.value;
  if (!s) return;
  try {
    await exportarSolicitudPdf(s);
  } catch {
    notify.error('No se pudo generar el PDF. Intente de nuevo.');
  }
}

onMounted(async () => {
  await cargar();
  await aplicarResaltado();
});

// Re-aplica el resaltado si llega un nuevo ?resaltar=ID estando ya en esta
// misma ruta (un cambio de query no vuelve a montar el componente).
watch(() => route.query.resaltar, (nuevo) => {
  if (nuevo) aplicarResaltado();
});

usePolling(() => {
  if (!cargando.value && !modalDetalle.value) cargarSilencioso();
}, 30000);
</script>

<style scoped>
.historial-page {
  background: #f5f7fb;
}

/* ── Header ── */
.historial-header-bar {
  width: 4px;
  align-self: stretch;
  min-height: 34px;
  border-radius: 999px;
  background: linear-gradient(180deg, #16468e, #2f70bb);
}
.historial-header-title { color: #1e293b; }
.dark .historial-header-title { color: #e2e8f0; }
.historial-header-sub { color: #64748b; }
.dark .historial-header-sub { color: #94a3b8; }
.historial-refresh {
  color: var(--rf-primary);
  background: #eef2ff;
  padding: .4rem .8rem;
  border-radius: 8px;
  border: 1px solid #e0e7ff;
}
.dark .historial-refresh {
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.15);
  border-color: rgba(255, 255, 255, 0.08);
}
.historial-refresh:hover {
  background: #e0e7ff;
}
.dark .historial-refresh:hover {
  background: rgba(99, 102, 241, 0.25);
}

.filtros-card {
  background: #fff;
  border: 1px solid #d4deea;
  box-shadow: 0 4px 16px rgba(22, 70, 142, .08);
  border-radius: 14px;
  position: relative;
  overflow: hidden;
}
.filtros-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, #0d2d6b, #16468e, #2f70bb);
  opacity: .6;
}

/* ── Tabla ── */
.tabla-card {
  background: #fff;
  border: 1px solid #d4deea;
  box-shadow: 0 4px 16px rgba(22, 70, 142, .08);
  border-radius: 14px;
  position: relative;
  overflow: hidden;
}
.dark .tabla-card,
.dark .filtros-card,
.dark .mobile-card,
.dark .empty-state-wrap {
  background: #111827;
  border-color: #1e293b;
}
.tabla-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, #0d2d6b, #16468e, #2f70bb);
  opacity: .6;
}

.table-row {
  border-top: 1px solid #f1f5f9;
  transition: background .15s ease, box-shadow .15s ease;
  position: relative;
}
.dark .table-row { border-top-color: #1e293b; }

/* ── Fila resaltada (actualización silenciosa en segundo plano) ── */
.table-row-resaltada {
  animation: hist-row-glow 2.2s ease-in-out 2;
  box-shadow: inset 3px 0 0 #D97706;
}
@keyframes hist-row-glow {
  0%, 100% { background: transparent; }
  50% { background: rgba(217, 119, 6, .12); }
}
.table-row:hover {
  background: #f0f5ff;
  box-shadow: inset 3px 0 0 #16468e;
}
.dark .table-row:hover { background: #1a2540; }
.table-row:hover .w-4\.h-4 {
  transform: translateX(3px);
  color: #16468e;
}

/* ── Cards móvil ── */
.mobile-card {
  background: #fff;
  border: 1px solid #d4deea;
  box-shadow: 0 3px 12px rgba(22, 70, 142, .07);
  transition: transform .2s ease, box-shadow .2s ease;
}
.mobile-card:active {
  transform: scale(.98);
  box-shadow: 0 6px 18px rgba(22, 70, 142, .12);
}

/* ── Estado badges ── */
.estado-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}
.estado-pendiente {
  background: #fef3c7;
  color: #b45309;
}
.estado-pendiente::before {
  content: '';
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #fbbf24;
}
.estado-negado {
  background: #fee2e2;
  color: #b91c1c;
}
.estado-negado::before {
  content: '';
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #ef4444;
}
.estado-en_espera {
  background: #dbeafe;
  color: #1d4ed8;
}
.estado-en_espera::before {
  content: '';
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #3b82f6;
}
.estado-completado {
  background: #dcfce7;
  color: #16a34a;
}
.estado-completado::before {
  content: '';
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #22c55e;
}
.dark .estado-pendiente { background: #422f0c; color: #fbbf24; }
.dark .estado-negado { background: #4c1d1d; color: #f87171; }
.dark .estado-en_espera { background: #1e3a5f; color: #60a5fa; }
.dark .estado-completado { background: #14532d; color: #4ade80; }

/* ── Skeleton ── */
.skeleton-row {
  background: #fff;
  border: 1px solid #edf1f7;
}
.skeleton-circle {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #e3e8f0;
  animation: skeletonPulse 1.5s ease-in-out infinite;
  flex-shrink: 0;
}
.skeleton-bar {
  background: #e3e8f0;
  border-radius: 4px;
  animation: skeletonPulse 1.5s ease-in-out infinite;
}
@keyframes skeletonPulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .5; }
}

/* ── Empty state ── */
.empty-state-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 3rem 1rem;
  position: relative;
  background: #fff;
  border: 1px solid #d4deea;
  border-radius: 18px;
  box-shadow: 0 4px 16px rgba(22, 70, 142, .08);
  overflow: hidden;
}
.empty-state-glow {
  position: absolute;
  top: -40px; left: 50%;
  transform: translateX(-50%);
  width: 200px; height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(126,179,255,0.1), transparent 70%);
  pointer-events: none;
}
.empty-state-icon {
  color: #2f70bb;
  background: linear-gradient(135deg, #e4f0ff, #dbeafe);
  box-shadow: 0 8px 20px rgba(47, 112, 187, .15);
}
.empty-state-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem 1rem;
  border-radius: 10px;
  background: linear-gradient(135deg, #16468e, #0d2d6b);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: transform .2s ease, box-shadow .2s ease;
  box-shadow: 0 4px 12px rgba(13, 45, 107, .25);
}
.empty-state-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(13, 45, 107, .35);
}

/* ── Paginación ── */
.pag-btn {
  padding: .35rem .7rem;
  font-size: .72rem;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  cursor: pointer;
  transition: all .15s ease;
}
.pag-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #cbd5e1;
}
.pag-btn:disabled {
  opacity: .4;
  cursor: not-allowed;
}

.pag-arrow-btn {
  display: grid;
  place-items: center;
  width: 30px; height: 30px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  transition: all .15s ease;
}
.pag-arrow-btn:hover:not(:disabled) { background: #f1f5f9; border-color: #cbd5e1; color: #16468e; }
.pag-arrow-btn:disabled { opacity: .4; cursor: not-allowed; }
.dark .pag-btn,
.dark .pag-arrow-btn { background: #1e293b; border-color: #334155; color: #94a3b8; }
.dark .pag-btn:hover:not(:disabled),
.dark .pag-arrow-btn:hover:not(:disabled) { background: #253449; border-color: #3b82f6; color: #93c5fd; }
.dark .pag-num-btn { color: #94a3b8; }
.dark .pag-num-btn:hover { background: #253449; }
.pag-num-btn {
  min-width: 30px; height: 30px;
  padding: 0 .3rem;
  border-radius: 8px;
  border: 1px solid transparent;
  background: transparent;
  font-size: .75rem;
  font-weight: 700;
  color: #64748b;
  cursor: pointer;
  transition: all .15s ease;
}
.pag-num-btn:hover { background: #f1f5f9; }
.pag-num-btn-active {
  background: #16468e;
  color: #fff;
  box-shadow: 0 3px 10px rgba(22, 70, 142, .3);
}
.pag-num-btn-active:hover { background: #16468e; }
.pag-ellipsis {
  color: #b0bccf;
  font-size: .75rem;
  font-weight: 700;
  padding: 0 .15rem;
}

/* ── Celda de fecha ── */
.fecha-cell { display: flex; align-items: center; gap: .35rem; }
.fecha-cell-dia { font-size: .72rem; color: #475569; font-weight: 500; line-height: 1.3; }
.fecha-cell-hora { font-size: .62rem; color: #94a3b8; line-height: 1.3; }
.dark .fecha-cell-dia { color: #cbd5e1; }
.dark .fecha-cell-hora { color: #64748b; }

/* ── Botón de acción (ver detalle) ── */
.accion-btn {
  display: inline-grid;
  place-items: center;
  width: 28px; height: 28px;
  border-radius: 8px;
  border: 1px solid #e0e7ff;
  background: #eef2ff;
  color: #16468e;
  cursor: pointer;
  transition: all .2s ease;
}
.accion-btn:hover { background: #dbe4ff; border-color: #b8c8de; transform: scale(1.06); }
.dark .accion-btn { background: #1e293b; border-color: #334155; color: #93c5fd; }
.dark .accion-btn:hover { background: #253449; border-color: #3b82f6; }

/* ── Animaciones ── */
.anim-fade-down { animation: fadeDown 0.5s cubic-bezier(.22,1,.36,1) both; }
.anim-slide-up  { animation: slideUp  0.5s cubic-bezier(.22,1,.36,1) both; }
.anim-row-in    { animation: rowIn   0.4s cubic-bezier(.22,1,.36,1) both; }

@keyframes fadeDown { from { opacity:0; transform:translateY(-18px); } to { opacity:1; transform:none; } }
@keyframes slideUp  { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:none; } }
@keyframes rowIn    { from { opacity:0; transform:translateX(-12px); } to { opacity:1; transform:none; } }

:deep(.el-input__wrapper) {
  box-shadow: 0 0 0 1px #dce7f2 inset;
  border-radius: 10px;
}
:deep(.el-input__wrapper:hover) {
  box-shadow: 0 0 0 1px #86b4e8 inset;
}

/* ── Filtros nativos ── */
.filter-label {
  display: block;
  font-size: .66rem;
  font-weight: 700;
  color: #8a9ab5;
  text-transform: uppercase;
  letter-spacing: .03em;
  margin-bottom: .3rem;
}
:deep(.filtros-card .el-input) { display: block; }
.filter-select {
  display: block;
  appearance: none;
  -webkit-appearance: none;
  padding: .5rem 2rem .5rem .8rem;
  font-size: .8rem;
  font-weight: 500;
  color: #475569;
  background: #fff;
  border: 1px solid #dce7f2;
  border-radius: 10px;
  cursor: pointer;
  transition: border-color .2s ease, box-shadow .2s ease;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238a9ab5' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right .6rem center;
  max-width: 150px;
}
.filter-select:hover {
  border-color: #86b4e8;
}
.filter-select:focus {
  outline: none;
  border-color: #16468e;
  box-shadow: 0 0 0 3px rgba(22, 70, 142, .1);
}

:deep(.filter-daterange.el-date-editor) {
  width: 210px;
  --el-date-editor-daterange-width: 210px;
}

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
.detalle-head-badge.estado-completado { background: #dcfce7; color: #16a34a; }
.detalle-head-badge.estado-negado { background: #fee2e2; color: #b91c1c; }

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

:deep(.historia-dialog) { border-radius: 18px; }
:deep(.historia-dialog .el-dialog__header) { padding: 1.1rem 1.3rem .6rem; margin: 0; }
:deep(.historia-dialog .el-dialog__title) { font-size: .9rem; font-weight: 800; color: #0d2d5e; }
:deep(.historia-dialog .el-dialog__body) { padding: 0 1.3rem 1.3rem; max-height: 60vh; overflow-y: auto; }
.historia-full-text { font-size: .8rem; color: #334155; line-height: 1.6; margin: 0; white-space: pre-wrap; }
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

/* Código de aceptación */
.detalle-code-bar {
  display: flex;
  align-items: center;
  gap: .5rem;
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  border: 1px solid #a7f3d0;
  border-radius: 10px;
  padding: .4rem .7rem;
}
.detalle-code-value {
  margin-left: auto;
  font-family: monospace;
  font-size: .82rem;
  font-weight: 800;
  color: #166534;
  background: #fff;
  padding: .15rem .5rem;
  border-radius: 6px;
  border: 1px solid #86efac;
}

/* Respuesta / Negación */
.detalle-respuesta {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  padding: .4rem .7rem;
}
.detalle-negacion {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: .4rem .7rem;
}

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

/* Adjuntos (Soportes) */
.adjunto-list { display: flex; flex-direction: column; gap: .3rem; }
.adjunto-row {
  display: flex;
  align-items: center;
  gap: .45rem;
  padding: .25rem;
  border-radius: 10px;
  transition: background .15s ease;
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
.adjunto-descargar-btn {
  display: grid;
  place-items: center;
  width: 28px; height: 28px;
  border-radius: 8px;
  border: 1px solid #e0e7ff;
  background: #eef2ff;
  color: #16468e;
  cursor: pointer;
  flex-shrink: 0;
  transition: all .2s ease;
}
.adjunto-descargar-btn:hover:not(:disabled) { background: #dbe4ff; }
.adjunto-descargar-btn:disabled { opacity: .6; cursor: default; }
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
</style>
