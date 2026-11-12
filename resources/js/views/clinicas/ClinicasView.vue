<template>
  <div class="h-full flex flex-col gap-2 p-3 sm:p-4 overflow-hidden clinic-page">

    <!-- ── Stat cards ── -->
    <div class="clinic-stats-bar shrink-0">
      <StatCard
        variant="pastel" tone="info" solid-icon
        label="Total de clínicas" :value="displayStats[0]" :comparacion="deltaTotal"
        :icon="HospitalIcon" :sparkline="crecimientoClinicas"
      />
      <StatCard
        variant="pastel" tone="success" solid-icon
        label="Con referencias" :value="displayStats[1]" :comparacion="deltaConReferencias"
        :icon="SendIcon" :sparkline="serieConReferencias"
      />
      <StatCard
        variant="pastel" tone="warning" solid-icon
        label="Nuevas este mes" :value="displayStats[2]" comparacion="= Altas recientes"
        :icon="PlusIcon" :sparkline="serieNuevasMes"
      />
      <StatCard
        variant="pastel" tone="violet" solid-icon
        label="Ciudades cubiertas" :value="displayStats[3]" comparacion="= Cobertura"
        :icon="MapPinIcon" :sparkline="serieCiudades"
      />
    </div>

    <!-- ── Mapa + Estadísticas + Tabla ── -->
    <div class="flex-1 flex gap-2 overflow-hidden">
    <div class="clinic-map-col shrink-0 flex flex-col gap-2 min-h-0">
      <MapaClinicas ref="mapaRef" :clinicas="clinicas" class="flex-1 min-h-0" />

      <!-- Estadísticas por ciudad -->
      <div class="clinic-city-panel shrink-0">
        <div class="clinic-city-head">
          <component :is="BarChartIcon" class="w-3.5 h-3.5 clinic-city-icon" />
          <p class="clinic-city-title">Estadísticas por ciudad</p>
        </div>
        <div class="clinic-city-list">
          <div v-for="(c, i) in estadisticasCiudad.lista" :key="c.ciudad" class="clinic-city-row">
            <span class="clinic-city-name">{{ c.ciudad }}</span>
            <div class="clinic-city-track">
              <div class="clinic-city-bar" :style="{ width: `${(c.total / estadisticasCiudad.max) * 100}%`, background: CIUDAD_COLORS[i % CIUDAD_COLORS.length] }"></div>
            </div>
            <span class="clinic-city-count">{{ c.total }}</span>
          </div>
          <p v-if="!estadisticasCiudad.lista.length" class="clinic-city-empty">Sin datos por ciudad</p>
        </div>
      </div>
    </div>
    <div class="flex-1 overflow-hidden clinic-table-panel flex flex-col">
      <!-- Encabezado del listado -->
      <div class="clinic-list-head shrink-0">
        <component :is="BuildingIcon" class="w-3.5 h-3.5 clinic-list-head-icon" />
        <p class="clinic-list-title">Listado de clínicas</p>
        <div class="clinic-head-actions">
          <el-tooltip content="Actualizar" placement="top">
            <button class="clinic-head-btn clinic-head-btn-icon" @click="cargar">
              <component :is="RefreshIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': cargando }" />
            </button>
          </el-tooltip>
          <button class="clinic-head-btn" :disabled="clinicasFiltradas.length === 0" @click="exportarExcel">
            <component :is="DownloadIcon" class="w-3.5 h-3.5" /> Exportar
          </button>
          <button class="clinic-head-btn" @click="abrirCargaMasiva">
            <component :is="UploadIcon" class="w-3.5 h-3.5" /> Carga masiva
          </button>
          <button class="clinic-head-btn clinic-head-btn-primary" @click="abrirNuevaClinica">
            <component :is="PlusIcon" class="w-3.5 h-3.5" /> Nueva clínica
          </button>
        </div>
        <div ref="filtrosWrap" class="clinic-filtros-wrap">
          <button class="clinic-filtros-btn" :class="{ 'clinic-filtros-btn-active': filtrosAbiertos || conteoFiltros > 0 }" @click="toggleFiltros">
            <component :is="SlidersIcon" class="w-3.5 h-3.5" />
            Filtros
            <span v-if="conteoFiltros > 0" class="clinic-filtros-badge">{{ conteoFiltros }}</span>
          </button>

        </div>
      </div>
      <!-- ── Barra de filtros inline: empuja la tabla, no se monta encima ── -->
      <Transition name="bulk-slide">
        <div v-if="filtrosAbiertos" class="clinic-filtros-bar">
          <div class="clinic-filtros-field">
            <label class="clinic-filtros-label">Ciudad</label>
            <el-select v-model="filtroCiudad" placeholder="Todas las ciudades" clearable filterable size="small" class="clinic-select">
              <el-option v-for="c in ciudadesUnicas" :key="c" :label="c" :value="c" />
            </el-select>
          </div>
          <div class="clinic-filtros-field clinic-filtros-field--grow">
            <label class="clinic-filtros-label">Buscar</label>
            <el-input
              v-model="filtroBuscar"
              placeholder="Clínica, ciudad, contacto..."
              class="clinic-search"
              clearable
              size="small"
            >
              <template #prefix>
                <component :is="SearchIcon" class="w-3.5 h-3.5 text-gray-400" />
              </template>
            </el-input>
          </div>
          <div class="clinic-filtros-field">
            <label class="clinic-filtros-label">Ordenar</label>
            <el-select v-model="sortKey" size="small" class="clinic-select">
              <el-option label="Nombre" value="nombre" />
              <el-option label="Ciudad" value="ciudad" />
            </el-select>
          </div>
          <div class="clinic-filtros-field clinic-filtros-field--dir">
            <el-select v-model="sortDir" size="small" class="clinic-select">
              <el-option label="Ascendente" value="asc" />
              <el-option label="Descendente" value="desc" />
            </el-select>
          </div>
          <button v-if="hayFiltros" class="clinic-limpiar" @click="limpiarFiltros">Limpiar</button>
        </div>
      </Transition>
      <!-- Loading -->
      <div v-if="cargando" class="clinic-table-loading">
        <div v-for="i in 6" :key="i" class="clinic-table-row-skeleton">
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
      <div v-else-if="clinicasPaginadas.length === 0" class="clinic-empty-wrap">
        <div class="clinic-empty-glow"></div>
        <div class="clinic-empty-icon w-16 h-16 rounded-2xl flex items-center justify-center mb-3 relative z-10">
          <component :is="BuildingIcon" class="w-8 h-8" />
        </div>
        <p class="font-bold text-base mb-1 relative z-10" style="color:#0d2d5e;">Sin clínicas</p>
        <p class="text-xs max-w-[280px] relative z-10" style="color:#64748b;">No hay instituciones en esta categoría.</p>
      </div>

      <!-- Lista en tarjetas -->
      <div v-else class="flex flex-col h-full overflow-hidden">
        <div class="clinic-selectbar shrink-0">
          <label class="clinic-selectbar-label" title="Seleccionar todas las visibles para verlas en el mapa">
            <input type="checkbox" :checked="todasSeleccionadas" @change="toggleSeleccionTodas" class="clinic-checkbox" />
            Seleccionar visibles en el mapa
          </label>
          <span v-if="seleccionadas.size" class="clinic-selectbar-count">{{ seleccionadas.size }} en el mapa</span>
        </div>
        <div class="clinic-card-scroll custom-scrollbar">
          <TransitionGroup name="clinic-row" tag="div" class="clinic-card-list" role="list" aria-label="Listado de clínicas">
            <article
              v-for="clinica in clinicasPaginadas"
              :key="clinica.id"
              role="listitem"
              class="clinic-card"
              :class="{ 'clinic-card-selected': seleccionadas.has(clinica.id) }"
            >
              <div class="clinic-card-identity">
                <input type="checkbox" :checked="seleccionadas.has(clinica.id)" :title="seleccionadas.has(clinica.id) ? 'Quitar del mapa' : 'Ver en el mapa'" @change="toggleSeleccion(clinica.id)" class="clinic-checkbox" />
                <div class="clinic-card-avatar" :style="avatarStyle(clinica.nombre)">
                  {{ iniciales(clinica.nombre) }}
                </div>
                <div class="clinic-card-idtext">
                  <button type="button" class="clinic-card-name" :title="clinica.nombre" @click="verDetalle(clinica)">{{ clinica.nombre }}</button>
                  <a :href="`mailto:${clinica.email}`" class="clinic-card-email" :title="clinica.email" @click.stop>{{ clinica.email }}</a>
                </div>
              </div>
              <div class="clinic-card-block clinic-card-info-block clinic-card-divider">
                <span class="clinic-card-label">Ciudad</span>
                <p class="clinic-card-value" :title="clinica.ciudad">{{ clinica.ciudad || '—' }}</p>
                <span class="clinic-card-label clinic-card-label-gap">Contacto</span>
                <p class="clinic-card-sub" :title="clinica.telefono">{{ clinica.telefono || '—' }}</p>
              </div>
              <div class="clinic-card-activity clinic-card-divider">
                <span class="clinic-card-sol" :title="`${clinica.solicitudes_count ?? 0} solicitudes`">{{ clinica.solicitudes_count ?? 0 }} solicitudes</span>
                <span class="clinic-act">
                  {{ formatFechaCorta(ultimaActividad(clinica)) }}
                  <span class="clinic-act-dot" :class="actividadClase(clinica)"></span>
                </span>
              </div>
              <div class="clinic-card-actions">
                <el-button circle size="small" title="Ver detalle" aria-label="Ver detalle de la clínica" @click="verDetalle(clinica)">
                  <component :is="EyeIcon" class="w-3.5 h-3.5" />
                </el-button>
                <el-dropdown trigger="click" placement="bottom-end" :popper-options="{ modifiers: [{ name: 'offset', options: { offset: [8, 8] } }] }" popper-class="clinic-acciones-menu" @command="(cmd: string) => manejarAccionFila(cmd, clinica)">
                  <el-button circle size="small" aria-label="Más acciones">
                    <component :is="MoreIcon" class="w-3.5 h-3.5" />
                  </el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item command="editar" :icon="EditIcon" class="clinic-accion-neutral">Editar</el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </div>
            </article>
          </TransitionGroup>
        </div>
        <!-- Pagination -->
        <div class="clinic-pagination">
          <span class="clinic-pagination-info">
            Mostrando {{ (paginaActual - 1) * itemsPorPagina + 1 }}–{{ Math.min(paginaActual * itemsPorPagina, clinicasFiltradas.length) }}
            de {{ clinicasFiltradas.length }} clínicas
          </span>
          <div class="clinic-pagination-controls">
            <button class="clinic-pagination-btn" :disabled="paginaActual === 1" @click="paginaActual--">
              <component :is="ChevronLeftIcon" class="w-3.5 h-3.5" />
            </button>
            <template v-for="(p, idx) in paginasVisibles" :key="idx">
              <button
                v-if="p !== '…'"
                class="clinic-pagination-btn clinic-page-num"
                :class="{ 'clinic-page-active': p === paginaActual }"
                @click="paginaActual = p"
              >{{ p }}</button>
              <span v-else class="clinic-page-ellipsis">…</span>
            </template>
            <button class="clinic-pagination-btn" :disabled="paginaActual === totalPaginas" @click="paginaActual++">
              <component :is="ChevronRightIcon" class="w-3.5 h-3.5" />
            </button>
          </div>
          <el-select v-model="itemsPorPagina" size="small" class="clinic-per-page">
            <el-option v-for="n in [10, 15, 25, 50]" :key="n" :label="`${n} por página`" :value="n" />
          </el-select>
        </div>
      </div>
    </div>
    </div>

    <!-- Modal: Detalle -->
    <el-dialog
      v-model="modalDetalle"
      width="640px"
      class="detalle-clinica-dialog"
      :show-close="false"
      append-to-body
      align-center
    >
      <template v-if="clinicaSeleccionada">
        <div class="detalle-clinica-content">
          <button type="button" class="detalle-close-btn" @click="modalDetalle = false">
            <component :is="XIcon" class="w-4 h-4" />
          </button>

          <div class="detalle-head">
            <div class="detalle-head-pattern"></div>
            <div class="detalle-head-icon">
              <component :is="BuildingIcon" class="w-6 h-6" />
            </div>
            <div class="detalle-head-info">
              <p class="detalle-head-title">Detalle de clínica</p>
              <p class="detalle-head-sub">{{ clinicaSeleccionada.nit || 'Sin NIT' }} · {{ clinicaSeleccionada.nombre }}</p>
            </div>
          </div>

          <!-- Body con cards -->
          <div class="detalle-body">
            <div class="detalle-cards-grid">
              <!-- Card: Información General -->
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-blue"><component :is="BuildingIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-blue">Información general</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Nombre</span><strong>{{ clinicaSeleccionada.nombre }}</strong></div>
                  <div class="data-row"><span>NIT</span><strong class="font-mono">{{ clinicaSeleccionada.nit }}</strong></div>
                  <div class="data-row"><span>Razón social</span><strong>{{ clinicaSeleccionada.razon_social || '—' }}</strong></div>
                  <div class="data-row"><span>Fecha</span><strong>{{ formatFecha(clinicaSeleccionada.created_at) }}</strong></div>
                </div>
              </div>

              <!-- Card: Ubicación -->
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-amber"><component :is="HospitalIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-amber">Ubicación</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Ciudad</span><strong>{{ clinicaSeleccionada.ciudad }}</strong></div>
                  <div class="data-row"><span>Departamento</span><strong>{{ clinicaSeleccionada.departamento }}</strong></div>
                  <div class="data-row"><span>Dirección</span><strong>{{ clinicaSeleccionada.direccion }}</strong></div>
                  <div class="data-row">
                    <span>Teléfono</span>
                    <a v-if="clinicaSeleccionada.telefono" :href="`tel:${clinicaSeleccionada.telefono}`" class="detalle-card-row-link">{{ clinicaSeleccionada.telefono }}</a>
                    <strong v-else>—</strong>
                  </div>
                </div>
              </div>

              <!-- Card: Persona a cargo -->
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-violet"><component :is="UserIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-violet">Persona a cargo</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Nombre</span><strong>{{ clinicaSeleccionada.representante_legal }}</strong></div>
                  <div class="data-row"><span>Cédula</span><strong class="font-mono">{{ clinicaSeleccionada.cedula_representante }}</strong></div>
                  <div class="data-row">
                    <span>Correo</span>
                    <a :href="`mailto:${clinicaSeleccionada.email}`" class="detalle-card-row-link">{{ clinicaSeleccionada.email }}</a>
                  </div>
                </div>
              </div>

              <!-- Card: Actividad -->
              <div class="detalle-card">
                <div class="detalle-card-head">
                  <span class="detalle-card-icon detalle-card-icon-slate"><component :is="ClipboardListIcon" class="w-3.5 h-3.5" /></span>
                  <p class="detalle-card-title detalle-card-title-slate">Actividad</p>
                </div>
                <div class="card-body">
                  <div class="data-row"><span>Referencias enviadas</span><strong>{{ clinicaSeleccionada.solicitudes_count ?? 0 }}</strong></div>
                  <div class="data-row"><span>Última referencia</span><strong>{{ clinicaSeleccionada.solicitudes_max_created_at ? formatFecha(clinicaSeleccionada.solicitudes_max_created_at) : 'Nunca' }}</strong></div>
                  <div class="data-row"><span>Último acceso al portal</span><strong>{{ clinicaSeleccionada.sessions_max_created_at ? formatFecha(clinicaSeleccionada.sessions_max_created_at) : 'Nunca' }}</strong></div>
                </div>
              </div>
            </div>

            <!-- Observaciones -->
            <div v-if="clinicaSeleccionada.observaciones" class="detalle-card mt-2">
              <div class="detalle-card-head">
                <span class="detalle-card-icon detalle-card-icon-blue"><component :is="EyeIcon" class="w-3.5 h-3.5" /></span>
                <p class="detalle-card-title detalle-card-title-blue">Observaciones</p>
              </div>
              <div class="card-body"><p class="card-text">{{ clinicaSeleccionada.observaciones }}</p></div>
            </div>

            <!-- Motivo rechazo -->
            <div v-if="clinicaSeleccionada.motivo_rechazo" class="detalle-card mt-2">
              <div class="detalle-card-head">
                <span class="detalle-card-icon detalle-card-icon-rose"><component :is="AlertTriangle" class="w-3.5 h-3.5" /></span>
                <p class="detalle-card-title detalle-card-title-rose">Motivo de rechazo</p>
              </div>
              <div class="card-body"><p class="card-text">{{ clinicaSeleccionada.motivo_rechazo }}</p></div>
            </div>
          </div>

          <div class="detalle-footer">
            <p class="detalle-footer-note">
              <component :is="ShieldIcon" class="w-3.5 h-3.5" />
              La información está protegida y será tratada confidencialmente.
            </p>
            <button type="button" class="detalle-cerrar-btn" @click="modalDetalle = false">
              <component :is="SendIcon" class="w-3.5 h-3.5" />
              Cerrar
            </button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- Modal: Editar clínica -->
    <el-dialog v-model="modalEditar" width="680px" :close-on-click-modal="false" class="nueva-clinica-dialog" align-center>
      <template #header>
        <div class="accion-head">
          <div class="accion-head-glow"></div>
          <div class="accion-head-icon accion-head-icon-blue">
            <component :is="EditIcon" class="w-5 h-5" />
          </div>
          <div class="accion-head-info">
            <p class="accion-head-title">Editar clínica</p>
            <p class="accion-head-sub">{{ formEditar.nit || 'Sin NIT' }} · {{ formEditar.nombre }}</p>
          </div>
        </div>
      </template>

      <div class="px-5 py-4">
        <el-form ref="formEditarRef" :model="formEditar" :rules="rulesEditar" label-position="top" size="small" autocomplete="off">
          <div class="flex items-center gap-2 mb-2">
            <div class="nueva-clinica-step-num">1</div>
            <p class="text-xs font-bold text-[#0D2D6B] uppercase tracking-widest">Datos de la institución</p>
            <div class="flex-1 h-px" style="background: linear-gradient(90deg, #c5cfdb, transparent);"></div>
          </div>
          <div class="grid grid-cols-3 gap-x-3 mb-1">
            <el-form-item prop="nit" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="BuildingIcon" class="w-3 h-3 text-[#16468E]" /> NIT</span></template>
              <el-input v-model="formEditar.nit" placeholder="900123456" clearable />
            </el-form-item>
            <el-form-item prop="nombre" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="BuildingIcon" class="w-3 h-3 text-[#16468E]" /> Nombre</span></template>
              <el-input v-model="formEditar.nombre" placeholder="Nombre de la clínica" clearable />
            </el-form-item>
            <el-form-item prop="telefono" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="PhoneIcon" class="w-3 h-3 text-[#16468E]" /> Teléfono</span></template>
              <el-input v-model="formEditar.telefono" placeholder="3101234567" clearable />
            </el-form-item>
          </div>
          <div class="grid grid-cols-3 gap-x-3 mb-1">
            <el-form-item prop="direccion" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MapPinIcon" class="w-3 h-3 text-[#16468E]" /> Dirección</span></template>
              <el-input v-model="formEditar.direccion" placeholder="Dirección completa" clearable />
            </el-form-item>
            <el-form-item prop="email" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MailIcon" class="w-3 h-3 text-[#16468E]" /> Correo</span></template>
              <el-input v-model="formEditar.email" type="email" placeholder="correo@clinica.com" clearable />
            </el-form-item>
            <el-form-item class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MapPinIcon" class="w-3 h-3 text-[#16468E]" /> Departamento</span></template>
              <el-select v-model="formEditar.departamento" placeholder="Seleccione" filterable class="w-full" @change="formEditar.ciudad = ''">
                <el-option v-for="dep in departamentos" :key="dep" :label="dep" :value="dep" />
              </el-select>
            </el-form-item>
          </div>
          <div class="grid grid-cols-2 gap-x-3 mb-1">
            <el-form-item class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MapPinIcon" class="w-3 h-3 text-[#16468E]" /> Ciudad</span></template>
              <el-select v-model="formEditar.ciudad" placeholder="Seleccione" filterable class="w-full" :disabled="!formEditar.departamento">
                <el-option v-for="c in ciudadesEditar" :key="c" :label="c" :value="c" />
              </el-select>
            </el-form-item>
          </div>

          <div class="flex items-center gap-2 mb-2 mt-3">
            <div class="nueva-clinica-step-num">2</div>
            <p class="text-xs font-bold text-[#0D2D6B] uppercase tracking-widest">Persona a cargo</p>
            <div class="flex-1 h-px" style="background: linear-gradient(90deg, #c5cfdb, transparent);"></div>
          </div>
          <div class="grid grid-cols-2 gap-x-3 mb-1">
            <el-form-item prop="representante_legal" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="UserIcon" class="w-3 h-3 text-[#16468E]" /> Nombre</span></template>
              <el-input v-model="formEditar.representante_legal" placeholder="Nombre de la persona a cargo" clearable />
            </el-form-item>
            <el-form-item prop="cedula_representante" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="IdCardIcon" class="w-3 h-3 text-[#16468E]" /> Cédula</span></template>
              <el-input v-model="formEditar.cedula_representante" placeholder="Número de cédula" clearable />
            </el-form-item>
          </div>
        </el-form>
      </div>

      <template #footer>
        <el-button @click="modalEditar = false">Cancelar</el-button>
        <el-button type="primary" :loading="procesandoEditar" @click="guardarEdicion">
          <component :is="CheckIcon" class="w-3.5 h-3.5 mr-1" />
          Guardar cambios
        </el-button>
      </template>
    </el-dialog>

    <!-- Modal: Nueva clínica -->
    <el-dialog v-model="modalNuevaClinica" width="680px" :close-on-click-modal="false" class="nueva-clinica-dialog" align-center>
      <template #header>
        <div class="accion-head">
          <div class="accion-head-glow"></div>
          <div class="accion-head-icon accion-head-icon-amber">
            <component :is="PlusIcon" class="w-5 h-5" />
          </div>
          <div class="accion-head-info">
            <p class="accion-head-title">Nueva clínica</p>
            <p class="accion-head-sub">Registra una institución individual</p>
          </div>
        </div>
      </template>

      <div class="px-5 py-4">
        <el-form ref="formNuevaRef" :model="formNueva" :rules="rulesNueva" label-position="top" size="small" autocomplete="off">
          <!-- Sección institución -->
          <div class="flex items-center gap-2 mb-2">
            <div class="nueva-clinica-step-num">1</div>
            <p class="text-xs font-bold text-[#0D2D6B] uppercase tracking-widest">Datos de la institución</p>
            <div class="flex-1 h-px" style="background: linear-gradient(90deg, #c5cfdb, transparent);"></div>
          </div>
          <div class="grid grid-cols-3 gap-x-3 mb-1">
            <el-form-item prop="nit" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="BuildingIcon" class="w-3 h-3 text-[#16468E]" /> NIT</span></template>
              <el-input v-model="formNueva.nit" placeholder="900123456" clearable autocomplete="off" />
            </el-form-item>
            <el-form-item prop="nombre" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="BuildingIcon" class="w-3 h-3 text-[#16468E]" /> Nombre / Razón social</span></template>
              <el-input v-model="formNueva.nombre" placeholder="Nombre de la clínica" clearable autocomplete="off" />
            </el-form-item>
            <el-form-item prop="telefono" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="PhoneIcon" class="w-3 h-3 text-[#16468E]" /> Teléfono</span></template>
              <el-input v-model="formNueva.telefono" placeholder="3101234567" clearable autocomplete="off" />
            </el-form-item>
          </div>
          <div class="grid grid-cols-3 gap-x-3 mb-1">
            <el-form-item prop="direccion" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MapPinIcon" class="w-3 h-3 text-[#16468E]" /> Dirección</span></template>
              <el-input v-model="formNueva.direccion" placeholder="Dirección completa" clearable autocomplete="off" />
            </el-form-item>
            <el-form-item prop="email" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MailIcon" class="w-3 h-3 text-[#16468E]" /> Correo</span></template>
              <el-input v-model="formNueva.email" type="email" placeholder="correo@clinica.com" clearable autocomplete="off" />
            </el-form-item>
            <el-form-item prop="email_confirmacion" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MailCheckIcon" class="w-3 h-3 text-[#16468E]" /> Confirmar correo</span></template>
              <el-input v-model="formNueva.email_confirmacion" type="email" placeholder="Repita el correo" clearable autocomplete="off" />
            </el-form-item>
          </div>

          <!-- Sección ubicación -->
          <div class="flex items-center gap-2 mb-2 mt-3">
            <div class="nueva-clinica-step-num">2</div>
            <p class="text-xs font-bold text-[#0D2D6B] uppercase tracking-widest">Ubicación</p>
            <div class="flex-1 h-px" style="background: linear-gradient(90deg, #c5cfdb, transparent);"></div>
          </div>
          <div class="grid grid-cols-2 gap-x-3 mb-1">
            <el-form-item prop="departamento" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MapPinIcon" class="w-3 h-3 text-[#16468E]" /> Departamento</span></template>
              <el-select v-model="formNueva.departamento" placeholder="Seleccione" filterable class="w-full" @change="formNueva.ciudad = ''">
                <el-option v-for="dep in departamentos" :key="dep" :label="dep" :value="dep" />
              </el-select>
            </el-form-item>
            <el-form-item prop="ciudad" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="MapPinIcon" class="w-3 h-3 text-[#16468E]" /> Ciudad / Municipio</span></template>
              <el-select v-model="formNueva.ciudad" placeholder="Seleccione" filterable class="w-full" :disabled="!formNueva.departamento">
                <el-option v-for="c in ciudadesDelDepartamento" :key="c" :label="c" :value="c" />
              </el-select>
            </el-form-item>
          </div>

          <!-- Sección representante -->
          <div class="flex items-center gap-2 mb-2 mt-3">
            <div class="nueva-clinica-step-num">3</div>
            <p class="text-xs font-bold text-[#0D2D6B] uppercase tracking-widest">Persona a cargo</p>
            <div class="flex-1 h-px" style="background: linear-gradient(90deg, #c5cfdb, transparent);"></div>
          </div>
          <div class="grid grid-cols-2 gap-x-3 mb-1">
            <el-form-item prop="representante_legal" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="UserIcon" class="w-3 h-3 text-[#16468E]" /> Nombre completo</span></template>
              <el-input v-model="formNueva.representante_legal" placeholder="Nombre de la persona a cargo" clearable autocomplete="off" />
            </el-form-item>
            <el-form-item prop="cedula_representante" class="mb-1 form-item-custom">
              <template #label><span class="flex items-center gap-1"><component :is="IdCardIcon" class="w-3 h-3 text-[#16468E]" /> Cédula</span></template>
              <el-input v-model="formNueva.cedula_representante" placeholder="Número de cédula" clearable autocomplete="off" />
            </el-form-item>
          </div>
        </el-form>
      </div>

      <template #footer>
        <el-button @click="modalNuevaClinica = false">Cancelar</el-button>
        <el-button type="warning" :loading="procesandoNueva" @click="guardarNuevaClinica">
          <component :is="CheckIcon" class="w-3.5 h-3.5 mr-1" />
          Crear clínica
        </el-button>
      </template>
    </el-dialog>

    <!-- Modal: Carga masiva -->
    <el-dialog v-model="modalCargaMasiva" width="820px" :close-on-click-modal="false" class="carga-masiva-dialog" align-center>
      <template #header>
        <div class="accion-head">
          <div class="accion-head-glow"></div>
          <div class="accion-head-icon accion-head-icon-green">
            <component :is="UploadIcon" class="w-5 h-5" />
          </div>
          <div class="accion-head-info">
            <p class="accion-head-title">Carga masiva de clínicas</p>
            <p class="accion-head-sub">Registra múltiples clínicas a la vez</p>
          </div>
        </div>
      </template>

      <div class="px-5 py-4 space-y-4">
        <!-- Zona de pegado -->
        <div class="carga-masiva-paste-zone">
          <div class="flex items-center gap-2 mb-2">
            <component :is="ClipboardListIcon" class="w-4 h-4 text-blue-600" />
            <p class="text-xs font-bold" style="color:#334e70;">Pegar datos desde Excel</p>
          </div>
          <p class="text-xs text-gray-500 mb-2">Pega celdas copiadas desde Excel. Columnas: <strong>NIT | Nombre | Email | Teléfono | Ciudad | Departamento | Dirección | Persona a cargo | Cédula</strong></p>
          <el-input
            v-model="textoPegado"
            type="textarea"
            :rows="4"
            placeholder="900123456\tClínica Ejemplo\tinfo@ejemplo.com\t3001234567\tBogotá\tCundinamarca\tCalle 123\tJuan Pérez\t12345678"
          />
          <el-button type="primary" size="small" class="mt-2" @click="procesarPegado" :disabled="!textoPegado.trim()">
            <component :is="CheckIcon" class="w-3.5 h-3.5 mr-1" />
            Procesar datos
          </el-button>
        </div>

        <!-- Tabla de revisión -->
        <div v-if="filasCarga.length > 0" class="carga-masiva-table-wrap">
          <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-bold" style="color:#334e70;">Clínicas a registrar ({{ filasCarga.length }})</p>
            <el-button type="danger" size="small" text @click="filasCarga = []">
              <component :is="XIcon" class="w-3 h-3 mr-0.5" />
              Limpiar
            </el-button>
          </div>
          <div class="overflow-auto carga-masiva-scroll" style="max-height: 280px;">
            <table class="carga-masiva-table">
              <thead>
                <tr>
                  <th class="carga-masiva-th">NIT</th>
                  <th class="carga-masiva-th">Nombre</th>
                  <th class="carga-masiva-th">Email</th>
                  <th class="carga-masiva-th">Teléfono</th>
                  <th class="carga-masiva-th">Ciudad</th>
                  <th class="carga-masiva-th">Depto</th>
                  <th class="carga-masiva-th">Dirección</th>
                  <th class="carga-masiva-th">Persona a cargo</th>
                  <th class="carga-masiva-th">Cédula</th>
                  <th class="carga-masiva-th"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(fila, idx) in filasCarga" :key="idx" class="carga-masiva-row">
                  <td class="carga-masiva-td"><input v-model="fila.nit" class="carga-masiva-input" placeholder="NIT" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.nombre" class="carga-masiva-input" placeholder="Nombre" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.email" class="carga-masiva-input" placeholder="Email" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.telefono" class="carga-masiva-input" placeholder="Teléfono" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.ciudad" class="carga-masiva-input" placeholder="Ciudad" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.departamento" class="carga-masiva-input" placeholder="Depto" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.direccion" class="carga-masiva-input" placeholder="Dirección" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.representante_legal" class="carga-masiva-input" placeholder="Persona a cargo" /></td>
                  <td class="carga-masiva-td"><input v-model="fila.cedula_representante" class="carga-masiva-input" placeholder="Cédula" /></td>
                  <td class="carga-masiva-td">
                    <button class="carga-masiva-remove" @click="filasCarga.splice(idx, 1)">
                      <component :is="XIcon" class="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Fila vacía para añadir manualmente -->
        <el-button v-if="filasCarga.length > 0" size="small" @click="agregarFila">
          <component :is="PlusIcon" class="w-3.5 h-3.5 mr-1" />
          Agregar fila
        </el-button>
      </div>

      <template #footer>
        <el-button @click="modalCargaMasiva = false">Cancelar</el-button>
        <el-button type="success" :loading="procesandoCarga" :disabled="filasCarga.length === 0" @click="enviarCargaMasiva">
          <component :is="UploadIcon" class="w-3.5 h-3.5 mr-1" />
          Registrar {{ filasCarga.length }} clínica(s)
        </el-button>
      </template>
    </el-dialog>

  </div>
</template>

<style scoped>
.clinic-page {
  background: linear-gradient(160deg, #eef4fc 0%, #e3edf8 40%, #f0f5fa 100%);
}

/* ── Acciones del encabezado del listado (vidrio sobre navy) ── */
.clinic-head-actions { display: flex; align-items: center; gap: 5px; margin-left: auto; }
.clinic-head-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 26px; padding: 0 11px;
  border-radius: 8px; border: 1px solid rgba(255,255,255,.16);
  background: rgba(255,255,255,.1);
  font-size: 11px; font-weight: 700; color: rgba(255,255,255,.85);
  cursor: pointer; transition: all .15s ease;
  font-family: inherit; white-space: nowrap;
}
.clinic-head-btn:hover:not(:disabled) { background: rgba(255,255,255,.2); color: #fff; }
.clinic-head-btn:disabled { opacity: .4; cursor: not-allowed; }
.clinic-head-btn-icon { padding: 0; width: 26px; justify-content: center; }
.clinic-head-btn-primary { background: #fff; border-color: #fff; color: #0d2d5e; }
.clinic-head-btn-primary:hover:not(:disabled) { background: #dbeafe; color: #0d2d5e; }

/* ── Botón Filtros ── */
.clinic-filtros-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 26px; padding: 0 11px;
  border-radius: 8px; border: 1px solid rgba(255,255,255,.16);
  background: rgba(255,255,255,.1);
  font-size: 11px; font-weight: 700; color: rgba(255,255,255,.85);
  cursor: pointer; transition: all .15s ease;
  font-family: inherit;
}
.clinic-filtros-btn:hover, .clinic-filtros-btn-active {
  background: #fff; border-color: #fff; color: #0d2d5e;
}
.clinic-filtros-btn-active .clinic-filtros-badge { background: #0d2d5e; color: #fff; }

/* ── Panel desplegable de filtros ── */
.clinic-filtros-wrap { position: relative; margin-left: 6px; }
.clinic-filtros-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 15px; height: 15px; padding: 0 4px;
  border-radius: 999px;
  background: #16468E; color: #fff;
  font-size: 8.5px; font-weight: 800;
}
.clinic-filtros-btn-active .clinic-filtros-badge { background: #fff; color: #16468E; }
.clinic-limpiar {
  font-size: 11.5px; font-weight: 700; color: #dc2626;
  background: none; border: none; cursor: pointer;
  font-family: inherit; padding: 4px 8px; border-radius: 7px;
  transition: background .15s ease;
}
.clinic-limpiar:hover { background: #fef2f2; }

/* ── Tabs ── */
/* ── Table panel ── */
.clinic-table-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(22,70,142,.08);
  padding: 4px;
}
.clinic-list-head {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px;
  border-radius: 9px;
  background: #16468E;
  border: 1px solid #123a78;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.12), 0 3px 12px rgba(13,45,107,.22);
  margin-bottom: 4px;
}
.clinic-list-head-icon {
  width: 24px; height: 24px; padding: 4px; box-sizing: border-box;
  color: #fff; background: rgba(255,255,255,.14); border-radius: 7px;
  border: 1px solid rgba(255,255,255,.2); flex-shrink: 0;
}
.clinic-list-title {
  font-size: 12.5px; font-weight: 800; color: #fff; margin: 0;
  white-space: nowrap; letter-spacing: .02em;
  text-shadow: 0 1px 2px rgba(0,0,0,.25);
}

/* ── Mapa ── */
.clinic-map-col {
  width: 380px;
  max-width: 40%;
}
@media (max-width: 1100px) {
  .clinic-map-col { display: none; }
}
/* El canvas del mapa cede alto al panel de estadísticas */
.clinic-map-col :deep(.mapa-canvas) { min-height: 140px; }

/* ── Estadísticas por ciudad ── */
.clinic-city-panel {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(22,70,142,.08);
  padding: 10px 12px;
}
.dark .clinic-city-panel { background: #161b28; border-color: rgba(255,255,255,.07); }
.clinic-city-head {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 10px; margin: -2px -2px 8px;
  border-radius: 9px;
  background: #16468E;
  border: 1px solid #123a78;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.12), 0 3px 12px rgba(13,45,107,.22);
}
.clinic-city-icon { color: rgba(255,255,255,.9); }
.clinic-city-title {
  font-size: 12.5px; font-weight: 800; color: #fff; margin: 0;
  letter-spacing: .02em; text-shadow: 0 1px 2px rgba(0,0,0,.25);
}
.clinic-city-list {
  display: flex; flex-direction: column; gap: 7px;
  max-height: 130px; overflow-y: auto;
}
.clinic-city-row {
  display: flex; align-items: center; gap: 8px;
}
.clinic-city-name {
  font-size: 10.5px; font-weight: 600; color: #475569;
  width: 72px; flex-shrink: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .clinic-city-name { color: #94a3b8; }
.clinic-city-track {
  flex: 1; height: 6px; border-radius: 999px;
  background: #f1f5f9; overflow: hidden;
}
.dark .clinic-city-track { background: rgba(255,255,255,.07); }
.clinic-city-bar {
  height: 100%; border-radius: 999px;
  min-width: 6px;
  transition: width .6s cubic-bezier(.22,1,.36,1);
}
.clinic-city-count {
  font-size: 10.5px; font-weight: 800; color: #1e293b;
  width: 20px; text-align: right; flex-shrink: 0;
}
.dark .clinic-city-count { color: #e2e8f0; }
.clinic-city-empty {
  font-size: 10.5px; color: #94a3b8; text-align: center; margin: 6px 0;
}

/* ── Table loading ── */
.clinic-table-loading {
  display: flex; flex-direction: column; gap: 6px;
  padding: 4px;
}
.clinic-table-row-skeleton {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border-radius: 8px;
  background: rgba(255,255,255,0.8);
  border: 1px solid #e2e8f0;
}

/* ── Tarjetas de clínica ── */
.clinic-selectbar {
  display: flex; align-items: center; gap: 10px;
  padding: 5px 12px;
  border-bottom: 1px solid #eef2f9;
}
.clinic-selectbar-label {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 10.5px; font-weight: 600; color: #486996;
  cursor: pointer; user-select: none;
}
.clinic-selectbar-count {
  font-size: 10px; font-weight: 700; color: #16468E;
  background: #e9f2ff; border-radius: 999px; padding: 2px 8px;
}
.clinic-card-scroll { flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 8px 10px 10px; }
.clinic-card-list { display: flex; flex-direction: column; gap: 8px; }
.clinic-card {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, .9fr) minmax(140px, .8fr) auto;
  grid-template-areas: "identity info activity actions";
  align-items: center;
  gap: 12px;
  min-height: 58px;
  padding: 6px 10px;
  border: 1px solid rgba(225,235,248,.7);
  border-radius: 9px;
  background: linear-gradient(115deg, #fff, #fbfdff);
  box-shadow: 0 3px 10px rgba(42,88,149,.045);
  transition: border-color .18s ease, box-shadow .18s ease;
}
.clinic-card:hover { border-color: #bfd4f5; box-shadow: 0 4px 14px rgba(42,88,149,.1); }
.clinic-card-selected { border-color: #93b9f5 !important; background: linear-gradient(110deg, #eef4ff, #f7faff) !important; box-shadow: inset 3px 0 0 #16468E; }
.clinic-card > div { min-width: 0; }
.clinic-card-identity { grid-area: identity; display: flex; align-items: center; gap: 8px; }
.clinic-card-avatar {
  width: 34px; height: 34px; border-radius: 9px;
  display: grid; place-items: center; flex-shrink: 0;
  font-size: 11px; font-weight: 800;
}
.clinic-card-idtext { min-width: 0; flex: 1; }
.clinic-card-name {
  display: block; max-width: 100%;
  padding: 0; border: 0; background: none;
  color: #102451; font: inherit; font-size: 13px; font-weight: 750;
  cursor: pointer; text-align: left;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.clinic-card-name:hover { color: #2563eb; }
.clinic-card-name:focus-visible { outline: 2px solid #60a5fa; outline-offset: 2px; border-radius: 3px; }
.clinic-card-email {
  display: block; margin-top: 2px;
  font-size: 10px; color: #94a3b8; text-decoration: none;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.clinic-card-email:hover { color: #16468E; text-decoration: underline; }
.clinic-card-block { min-width: 0; }
.clinic-card-info-block { grid-area: info; }
.clinic-card-divider { padding-left: 15px; border-left: 1px solid #dfe8f7; }
.clinic-card-label { display: block; color: #6c87b0; font-size: 8.5px; font-weight: 600; text-transform: uppercase; line-height: 1.3; letter-spacing: .03em; }
.clinic-card-label-gap { margin-top: 5px; }
.clinic-card-value { margin: 3px 0 0; color: #18345e; font-size: 12px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.clinic-card-sub { margin: 2px 0 0; color: #728bb3; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.clinic-card-activity { grid-area: activity; display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.clinic-card-sol {
  display: inline-flex; align-items: center;
  padding: 3px 9px; border-radius: 999px;
  background: #e9f2ff; color: #1e4688;
  font-size: 10px; font-weight: 750; white-space: nowrap;
}
.clinic-card-actions { grid-area: actions; display: flex; align-items: center; gap: 5px; }
.clinic-card-actions :deep(.el-button) { width: 30px; height: 30px; border-color: #dbe5f5; background: #fff; color: #244671; }
.clinic-card-actions :deep(.el-button:hover) { color: #2563eb; border-color: #93b9f5; background: #f0f6ff; }

@media (max-width: 900px) {
  .clinic-card { grid-template-columns: minmax(0, 1.3fr) minmax(0, .9fr) auto; grid-template-areas: "identity activity actions" "info info info"; gap: 8px 12px; }
  .clinic-card-activity { flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
}
@media (max-width: 560px) {
  .clinic-card { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "identity actions" "info info" "activity activity"; align-items: start; gap: 8px; padding: 12px; border-radius: 14px; box-shadow: 0 6px 16px rgba(42,88,149,.09); }
  .clinic-card-name { font-size: 14.5px; font-weight: 800; }
  .clinic-card-avatar { width: 38px; height: 38px; }
  .clinic-card-actions { align-self: start; gap: 6px; }
  .clinic-card-actions :deep(.el-button) { width: 32px; height: 32px; }
  .clinic-card-divider { padding-left: 0; border-left: 0; }
  .clinic-card-info-block { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 12px; padding: 8px 10px; border-left: 3px solid #93b9f5; border-radius: 10px; background: rgba(238,244,255,.7); }
  .clinic-card-info-block .clinic-card-label-gap { margin-top: 0; }
  .clinic-card-activity { flex-direction: row; align-items: center; justify-content: space-between; gap: 8px; padding-top: 8px; border-top: 1px dashed rgba(100,130,180,.35); }
  .dark .clinic-card-info-block { border-left-color: #4d8ef7; background: rgba(255,255,255,.045); }
  .dark .clinic-card-activity { border-top-color: rgba(255,255,255,.16); }
}

/* ── Vista móvil de la página: todo apilado y con scroll de página ── */
@media (max-width: 700px) {
  .clinic-page.clinic-page { overflow-y: auto; overflow-x: hidden; padding: 10px; }
  .clinic-stats-bar { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
  .clinic-stats-bar :deep(.stat-card-pastel) { min-width: 0; min-height: 76px; }
  .clinic-page > .flex-1 { flex: none; min-height: 560px; overflow: visible; }
  .clinic-table-panel { min-height: 560px; }
  .clinic-list-head { flex-wrap: wrap; gap: 8px; padding: 10px; }
  .clinic-list-title { flex: 1; font-size: 13px; }
  .clinic-filtros-wrap { order: 2; }
  .clinic-head-actions { order: 3; flex-basis: 100%; margin-left: 0; flex-wrap: wrap; }
  .clinic-head-actions .clinic-head-btn { flex: 1 1 calc(50% - 5px); justify-content: center; height: 32px; }
  .clinic-head-actions .clinic-head-btn-icon { flex: 0 0 36px; }
  .clinic-head-actions .clinic-head-btn-primary { flex-basis: 100%; }
}

/* ── Modal detalle ── */
:deep(.detalle-clinica-dialog) {
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(11, 35, 73, .4), 0 0 0 1px rgba(255,255,255,.08);
}
:deep(.detalle-clinica-dialog .el-dialog__header) { display: none; }
:deep(.detalle-clinica-dialog .el-dialog__body) { padding: 0; overflow: hidden; }
.detalle-clinica-content { position: relative; overflow: hidden; background: #fff; }

.detalle-close-btn {
  position: absolute;
  top: .9rem; right: .9rem;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 32px; height: 32px;
  border-radius: 50%;
  border: 1px solid rgba(15,23,42,.08);
  background: #fff;
  color: #94A3B8;
  cursor: pointer;
  transition: all .2s ease;
  box-shadow: 0 2px 8px rgba(15,23,42,.08);
}
.detalle-close-btn:hover { color: #DC2626; border-color: #FCA5A5; background: #FEF2F2; }

/* Header claro — mismo estilo que el modal de detalle de Solicitudes */
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

/* Body */
.detalle-body {
  padding: 16px 20px 4px;
  max-height: min(60vh, 560px);
  overflow-y: auto;
}

.detalle-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: .6rem;
  padding: .9rem 1.3rem;
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
  padding: .55rem 1.2rem;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #4F46E5, #7C3AED);
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(124, 58, 237, .3);
  transition: all .2s ease;
}
.detalle-cerrar-btn:hover {
  background: linear-gradient(135deg, #5B52F0, #8B47E8);
  box-shadow: 0 6px 20px rgba(124, 58, 237, .4);
}
.detalle-cards-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}

/* Card base — mismo estilo que el modal de detalle de Solicitudes */
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

.detalle-card-row-link {
  font-size: .68rem; font-weight: 600; color: var(--rf-primary);
  text-align: right; word-break: break-word; text-decoration: none;
}
.detalle-card-row-link:hover { text-decoration: underline; }

/* El estilo del scroll es global en tailwind.css (invisible hasta hover, tipo Element Plus) */

/* ── Animaciones ── */
.anim-slide-up { animation: slideUp .5s cubic-bezier(.22,1,.36,1) both; }
@keyframes slideUp { from { opacity:0; transform: translateY(14px); } to { opacity:1; transform:none; } }
.anim-card-in { animation: cardIn 0.5s cubic-bezier(.22,1,.36,1) both; }
@keyframes cardIn {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: none; }
}

/* ── Shimmer ── */
.shimmer-box, .shimmer-bar {
  position: relative;
  overflow: hidden;
  background: #e6ebf3;
}
.shimmer-box { border-radius: 6px; }
.shimmer-bar { border-radius: 4px; }
.shimmer-box::after, .shimmer-bar::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.6) 50%, transparent 100%);
  animation: shimmer 1.8s ease-in-out infinite;
}
@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

/* ── Stat cards ── */
.clinic-stats-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: .75rem;
}

/* Mientras la barra de filtros entra y empuja el layout, bloquear clics
   sobre el contenido que se mueve */
.bulk-slide-enter-active ~ * {
  pointer-events: none;
}
.bulk-slide-enter-active, .bulk-slide-leave-active {
  transition: all .25s ease;
}
.bulk-slide-enter-from, .bulk-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ── Row transitions ── */
.clinic-row-enter-active, .clinic-row-leave-active {
  transition: all .3s ease;
}
.clinic-row-enter-from {
  opacity: 0;
  transform: translateX(-12px);
}
.clinic-row-leave-to {
  opacity: 0;
  transform: translateX(12px);
}

/* ── Pagination ── */
.clinic-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .5rem .8rem;
  border-top: 1px solid #f1f5f9;
  background: #fafbfc;
}
.clinic-pagination-info {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}
.clinic-pagination-controls {
  display: flex;
  align-items: center;
  gap: .4rem;
}
.clinic-pagination-btn {
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
.clinic-pagination-btn:hover:not(:disabled) {
  border-color: #16468E;
  color: #16468E;
  background: #f0f5ff;
}
.clinic-pagination-btn:disabled {
  opacity: .4;
  cursor: not-allowed;
}
.clinic-pagination-page {
  font-size: 12px;
  font-weight: 600;
  color: #334e70;
  min-width: 60px;
  text-align: center;
}
.clinic-page-num {
  font-family: inherit;
  font-size: 11px; font-weight: 700;
}
.clinic-page-active {
  background: linear-gradient(135deg, #0D2D6B, #16468E) !important;
  border-color: #16468E !important;
  color: #fff !important;
  box-shadow: 0 2px 8px rgba(13,45,107,.3);
}
.clinic-page-ellipsis {
  font-size: 11px; color: #94a3b8; padding: 0 2px;
}
.clinic-per-page { width: 104px; }
.clinic-per-page :deep(.el-select__wrapper) {
  min-height: 26px !important;
  font-size: 11px;
}

/* ── Columnas nuevas ── */
.clinic-checkbox {
  width: 16px; height: 16px;
  border-radius: 4px;
  border: 1.5px solid #cbd5e1;
  cursor: pointer;
  accent-color: #16468E;
  flex-shrink: 0;
}
.dark .clinic-selectbar { border-bottom-color: rgba(255,255,255,.07); }
.dark .clinic-selectbar-label { color: #94a3b8; }
.dark .clinic-selectbar-count { background: rgba(96,165,250,.16); color: #93c5fd; }
.dark .clinic-card { background: linear-gradient(115deg, #192439, #162133); border-color: #293b56; box-shadow: 0 3px 10px rgba(0,0,0,.12); }
.dark .clinic-card:hover { border-color: #4771ab; }
.dark .clinic-card-selected { border-color: #3d6cb8 !important; background: linear-gradient(110deg, #17263f, #182135) !important; box-shadow: inset 3px 0 0 #4d8ef7; }
.dark .clinic-card-name { color: #dbe7fb; }
.dark .clinic-card-name:hover { color: #93c5fd; }
.dark .clinic-card-email { color: #6d84a8; }
.dark .clinic-card-email:hover { color: #93c5fd; }
.dark .clinic-card-divider { border-left-color: #314666; }
.dark .clinic-card-label { color: #7d95bb; }
.dark .clinic-card-value { color: #d3e1f5; }
.dark .clinic-card-sub { color: #8ba3c9; }
.dark .clinic-card-sol { background: #1d3454; color: #9dc1f7; }
.dark .clinic-card-actions :deep(.el-button) { border-color: #314666; background: #1c2d46; color: #bcd1ef; }
.dark .clinic-card-actions :deep(.el-button:hover) { border-color: #4771ab; background: #263d5d; color: #93c5fd; }
.clinic-act {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 600; color: #475569; white-space: nowrap;
}
.dark .clinic-act { color: #94a3b8; }
.clinic-act-dot {
  width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
.clinic-act-dot--ok { background: #22c55e; box-shadow: 0 0 0 2px #dcfce7; }
.clinic-act-dot--warn { background: #f59e0b; box-shadow: 0 0 0 2px #fef3c7; }
.clinic-act-dot--old { background: #ef4444; box-shadow: 0 0 0 2px #fee2e2; }

/* ── Nueva clínica ── */
:deep(.nueva-clinica-dialog) { border-radius: 16px; overflow: hidden; box-shadow: 0 32px 80px rgba(11,35,73,.35); }
:deep(.nueva-clinica-dialog .el-dialog__header) { margin: 0; padding: 0; border: none; }
:deep(.nueva-clinica-dialog .el-dialog__body) { padding: 0; }
:deep(.nueva-clinica-dialog .el-dialog__headerbtn) { top: 12px; right: 14px; z-index: 30; }
:deep(.nueva-clinica-dialog .el-dialog__headerbtn .el-dialog__close) { color: #fff; font-size: 1.4rem; font-weight: 700; }

.nueva-clinica-step-num {
  width: 24px; height: 24px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 800; color: #0D2D6B;
  background: #f1f5f9; border: 1px solid #e2e8f0;
}

.form-item-custom :deep(.el-input__wrapper),
.form-item-custom :deep(.el-select__wrapper) {
  border-radius: 10px;
  background: #f8fafc !important;
  box-shadow: inset 2px 2px 4px rgba(163,177,198,0.3), inset -2px -2px 4px rgba(255,255,255,0.7);
  transition: all 0.2s ease;
}
.form-item-custom :deep(.el-input__wrapper:hover),
.form-item-custom :deep(.el-select__wrapper:hover) {
  box-shadow: inset 2px 2px 4px rgba(163,177,198,0.4), inset -2px -2px 4px rgba(255,255,255,0.8);
}
.form-item-custom :deep(.el-input__wrapper.is-focus),
.form-item-custom :deep(.el-select__wrapper.is-focus) {
  box-shadow: inset 3px 3px 5px rgba(163,177,198,0.4), inset -3px -3px 5px rgba(255,255,255,0.85), 0 0 0 1.5px rgba(13,45,107,0.12);
}
.form-item-custom :deep(.el-input__prefix-inner) { color: #16468E; }
.form-item-custom :deep(.el-input__inner) { background: transparent !important; }
.form-item-custom :deep(.el-form-item__label)::before { display: none !important; content: '' !important; }

/* ── Carga masiva ── */
:deep(.carga-masiva-dialog) { border-radius: 16px; overflow: hidden; box-shadow: 0 32px 80px rgba(11,35,73,.35); }
:deep(.carga-masiva-dialog .el-dialog__header) { margin: 0; padding: 0; border: none; }
:deep(.carga-masiva-dialog .el-dialog__body) { padding: 0; }
:deep(.carga-masiva-dialog .el-dialog__headerbtn) { top: 12px; right: 14px; z-index: 30; }
:deep(.carga-masiva-dialog .el-dialog__headerbtn .el-dialog__close) { color: #fff; font-size: 1.4rem; font-weight: 700; }

.carga-masiva-paste-zone {
  background: #f8fafc;
  border: 1px dashed #c4d4e8;
  border-radius: 12px;
  padding: 14px 16px;
}

.carga-masiva-table-wrap {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px;
}

.carga-masiva-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 12px;
}
.carga-masiva-th {
  padding: 6px 8px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #64748b;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 5;
}
.carga-masiva-row { transition: background .15s ease; }
.carga-masiva-row:hover { background: #f8fafc; }

.carga-masiva-td {
  padding: 4px 6px;
  border-bottom: 1px solid #f1f5f9;
  white-space: nowrap;
}

.carga-masiva-input {
  width: 100%;
  min-width: 80px;
  padding: 5px 8px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 12px;
  color: #1e293b;
  background: #fff;
  transition: border-color .2s ease, box-shadow .2s ease;
  outline: none;
}
.carga-masiva-input:focus {
  border-color: #16468E;
  box-shadow: 0 0 0 2px rgba(22,70,142,.1);
}
.carga-masiva-input::placeholder { color: #cbd5e1; }

.carga-masiva-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border: none;
  border-radius: 6px;
  background: #fee2e2;
  color: #dc2626;
  cursor: pointer;
  transition: background .2s ease;
}
.carga-masiva-remove:hover { background: #fecaca; }

/* ── Shared accion-head (if not already defined elsewhere) ── */
.accion-head {
  position: relative;
  background: linear-gradient(120deg, #dbeafe 0%, #e0e7ff 55%, #ede9fe 100%);
  padding: 1.1rem 1.3rem;
  display: flex;
  align-items: center;
  gap: .8rem;
  overflow: hidden;
}
.accion-head-glow { position: absolute; top: -40px; right: -30px; width: 140px; height: 140px; border-radius: 50%; background: rgba(255,255,255,.5); filter: blur(10px); }
.accion-head-icon {
  width: 2.4rem; height: 2.4rem; border-radius: 12px;
  display: grid; place-items: center; flex-shrink: 0; z-index: 1;
  color: #fff;
  box-shadow: 0 6px 16px rgba(79, 70, 229, .3);
}
.accion-head-icon-blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.accion-head-icon-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.accion-head-icon-green { background: linear-gradient(135deg, #22c55e, #16a34a); }
.accion-head-icon-red { background: linear-gradient(135deg, #ef4444, #dc2626); }
.accion-head-info { flex: 1; z-index: 1; min-width: 0; }
.accion-head-title { margin: 0; color: #1E1B4B; font-size: .9rem; font-weight: 800; }
.accion-head-sub { margin: .1rem 0 0; color: #4c4a6e; font-size: .68rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Empty state ── */
.clinic-empty-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2rem 1rem;
  position: relative;
  background: #fff;
  border: 1px dashed #d4deea;
  border-radius: 12px;
  overflow: hidden;
  height: 100%;
}
.clinic-empty-glow {
  position: absolute; top: -40px; left: 50%;
  transform: translateX(-50%);
  width: 200px; height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(126,179,255,0.1), transparent 70%);
  pointer-events: none;
}
.clinic-empty-icon {
  color: #2f70bb;
  background: linear-gradient(135deg, #e4f0ff, #dbeafe);
  box-shadow: 0 8px 20px rgba(47, 112, 187, .15);
}
.clinic-empty-btn {
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
.clinic-empty-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(13, 45, 107, .35);
}

</style>

<!-- Panel de filtros teleportado a <body>: sin scoped porque sale del árbol del componente -->
<style>
.clinic-filtros-bar {
  display: flex; align-items: flex-end; gap: 12px;
  padding: 12px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #eef1f6;
}
.dark .clinic-filtros-bar { background: rgba(255,255,255,0.03); border-bottom-color: rgba(255,255,255,0.07); }
.clinic-filtros-field { display: flex; flex-direction: column; gap: 5px; width: 190px; flex-shrink: 0; }
.clinic-filtros-field--grow { width: auto; flex: 1; min-width: 200px; }
.clinic-filtros-field--dir { width: 140px; }
.clinic-filtros-label {
  font-size: 10.5px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: 0.05em;
}
.dark .clinic-filtros-label { color: #94a3b8; }
.clinic-filtros-bar .clinic-select,
.clinic-filtros-bar .clinic-search { width: 100%; min-width: 0; max-width: none; }
.clinic-filtros-bar .el-select__wrapper,
.clinic-filtros-bar .el-input__wrapper {
  border-radius: 9px !important;
  min-height: 32px;
}
.clinic-filtros-bar .clinic-limpiar {
  font-size: 11px; font-weight: 700; color: #dc2626;
  background: none; border: none; cursor: pointer;
  font-family: inherit; padding: 7px 10px; border-radius: 7px;
  transition: background .15s ease; flex-shrink: 0;
  margin-bottom: 1px;
}
.clinic-filtros-bar .clinic-limpiar:hover { background: #fef2f2; }
.dark .clinic-filtros-bar .clinic-limpiar:hover { background: rgba(220,38,38,0.15); }

/* ── Menú de acciones por fila (popper teleportado a <body>) ── */
.el-popper.clinic-acciones-menu,
.clinic-acciones-menu {
  min-width: 182px;
  border-radius: 14px !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 18px 44px rgba(15, 23, 42, .18), 0 4px 10px rgba(15, 23, 42, .06) !important;
  overflow: hidden;
}
.clinic-acciones-menu .el-dropdown-menu {
  padding: 6px !important;
}
.clinic-acciones-menu .el-dropdown-menu__item {
  display: flex;
  align-items: center;
  border-radius: 9px;
  margin: 2px 0;
  padding: 7px 10px;
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.3;
  gap: 10px;
  color: #334155;
  transition: background .15s ease, transform .15s ease;
}
.clinic-acciones-menu .el-dropdown-menu__item:hover {
  transform: translateX(2px);
}
.clinic-acciones-menu .el-dropdown-menu__item i.el-icon,
.clinic-acciones-menu .el-dropdown-menu__item svg {
  font-size: 13px;
  margin-right: 0;
}
.clinic-acciones-menu .el-dropdown-menu__item svg {
  width: 27px;
  height: 27px;
  padding: 6px;
  border-radius: 8px;
  box-sizing: border-box;
  flex-shrink: 0;
}
.clinic-acciones-menu .el-dropdown-menu__item--divided {
  margin-top: 6px;
  border-top-color: #eef1f5;
}
.clinic-acciones-menu .el-dropdown-menu__item--divided::before {
  margin-bottom: 6px;
}
.clinic-acciones-menu .clinic-accion-neutral { color: #334155 !important; }
.clinic-acciones-menu .clinic-accion-neutral svg { background: #e0e9f8; color: #16468E; border: 1px solid #cbdaf0; }
.clinic-acciones-menu .clinic-accion-neutral:not(.is-disabled):hover { background: #eef3fb !important; color: #16468E !important; }

html.dark .clinic-acciones-menu,
html.dark .el-popper.clinic-acciones-menu {
  background: #161b28 !important;
  border-color: rgba(255,255,255,0.08) !important;
  box-shadow: 0 18px 44px rgba(0,0,0,.55) !important;
}
html.dark .clinic-acciones-menu .el-dropdown-menu__item { color: #cbd5e1; }
html.dark .clinic-acciones-menu .el-dropdown-menu__item--divided { border-top-color: rgba(255,255,255,0.07); }
html.dark .clinic-acciones-menu .clinic-accion-neutral { color: #cbd5e1 !important; }
html.dark .clinic-acciones-menu .clinic-accion-neutral svg { background: rgba(59,130,246,0.16); color: #93c5fd; border-color: rgba(59,130,246,0.25); }
html.dark .clinic-acciones-menu .clinic-accion-neutral:not(.is-disabled):hover { background: rgba(255,255,255,0.06) !important; color: #93c5fd !important; }
</style>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { usePolling } from '@/lib/usePolling';
import { actualizarSiCambio } from '@/lib/silentRefresh';
import { useAuthStore } from '@/stores/auth';
import { useStorage, useDebounceFn } from '@vueuse/core';
import notify from '@/plugins/toast';
import {
  RefreshCw as RefreshIcon,
  Check as CheckIcon,
  X as XIcon,
  Eye as EyeIcon,
  Building2 as BuildingIcon,
  Hospital as HospitalIcon,
  AlertTriangle,
  Search as SearchIcon,
  Upload as UploadIcon,
  ClipboardList as ClipboardListIcon,
  Plus as PlusIcon,
  Mail as MailIcon,
  MailCheck as MailCheckIcon,
  Phone as PhoneIcon,
  User as UserIcon,
  MapPin as MapPinIcon,
  IdCard as IdCardIcon,
  Download as DownloadIcon,
  Edit as EditIcon,
  ChevronLeft as ChevronLeftIcon,
  ChevronRight as ChevronRightIcon,
  Shield as ShieldIcon,
  Send as SendIcon,
  MoreVertical as MoreIcon,
  BarChart3 as BarChartIcon,
  SlidersHorizontal as SlidersIcon,
} from '@lucide/vue';
import http from '@/plugins/axios';
import type { FormInstance, FormRules } from 'element-plus';
import StatCard from '@/components/ui/StatCard.vue';
import MapaClinicas from '@/components/clinicas/MapaClinicas.vue';

interface Clinica {
  id: number;
  nit: string | null;
  nombre: string;
  razon_social: string;
  email: string;
  telefono: string;
  ciudad: string;
  departamento: string;
  direccion: string;
  representante_legal: string;
  cedula_representante: string;
  observaciones?: string;
  especialidades?: string[];
  estado: 'pendiente' | 'activa' | 'rechazada';
  motivo_rechazo?: string;
  latitud?: number | null;
  longitud?: number | null;
  created_at: string;
  solicitudes_count?: number;
  solicitudes_max_created_at?: string | null;
  sessions_max_created_at?: string | null;
}

const clinicas = ref<Clinica[]>([]);
const mapaRef = ref<InstanceType<typeof MapaClinicas> | null>(null);
const cargando = ref(false);
const modalDetalle = ref(false);
const modalCargaMasiva = ref(false);
const clinicaSeleccionada = ref<Clinica | null>(null);
const textoPegado = ref('');
const filasCarga = ref<Array<{ nit: string; nombre: string; email: string; telefono: string; ciudad: string; departamento: string; direccion: string; representante_legal: string; cedula_representante: string }>>([]);
const procesandoCarga = ref(false);

const modalNuevaClinica = ref(false);
const procesandoNueva = ref(false);
const formNuevaRef = ref<FormInstance>();
const formNueva = reactive({
  nit: '',
  nombre: '',
  email: '',
  email_confirmacion: '',
  telefono: '',
  departamento: '',
  ciudad: '',
  direccion: '',
  representante_legal: '',
  cedula_representante: '',
});

const modalEditar = ref(false);
const procesandoEditar = ref(false);
const formEditarRef = ref<FormInstance>();
const formEditar = reactive({
  id: 0,
  nit: '',
  nombre: '',
  email: '',
  telefono: '',
  departamento: '',
  ciudad: '',
  direccion: '',
  representante_legal: '',
  cedula_representante: '',
});
const rulesEditar: FormRules = {
  nombre: [{ required: true, message: 'Obligatorio', trigger: 'blur' }],
  email: [{ required: true, message: 'Obligatorio', trigger: 'blur' }, { type: 'email', message: 'Correo inválido', trigger: 'blur' }],
};

const sortKey = ref<'nombre' | 'nit' | 'ciudad'>('nombre');
const sortDir = ref<'asc' | 'desc'>('asc');
const paginaActual = ref(1);
const itemsPorPagina = ref(10);
watch(itemsPorPagina, () => { paginaActual.value = 1; });

const colombiaDatos: Record<string, string[]> = {
  'Amazonas': ['Leticia', 'Puerto Nariño'],
  'Antioquia': ['Medellín', 'Bello', 'Itagüí', 'Envigado', 'Apartadó', 'Turbo', 'Rionegro', 'Caucasia', 'Marinilla', 'El Carmen de Viboral', 'Sabaneta', 'Copacabana', 'Girardota', 'Barbosa', 'La Estrella', 'Caldas'],
  'Arauca': ['Arauca', 'Saravena', 'Tame', 'Arauquita', 'Fortul'],
  'Atlántico': ['Barranquilla', 'Soledad', 'Malambo', 'Sabanalarga', 'Baranoa', 'Puerto Colombia'],
  'Bolívar': ['Cartagena', 'Magangué', 'Turbaco', 'El Carmen de Bolívar', 'Mompós'],
  'Boyacá': ['Tunja', 'Duitama', 'Sogamoso', 'Chiquinquirá', 'Paipa', 'Moniquirá'],
  'Caldas': ['Manizales', 'Villamaría', 'Chinchiná', 'Riosucio', 'Salamina', 'Aguadas'],
  'Caquetá': ['Florencia', 'San Vicente del Caguán', 'Puerto Rico', 'Belén de los Andaquíes'],
  'Casanare': ['Yopal', 'Aguazul', 'Villanueva', 'Tauramena', 'Paz de Ariporo'],
  'Cauca': ['Popayán', 'Santander de Quilichao', 'Puerto Tejada', 'Patía', 'El Tambo'],
  'Cesar': ['Valledupar', 'Aguachica', 'Bosconia', 'Codazzi', 'La Jagua de Ibirico'],
  'Chocó': ['Quibdó', 'Istmina', 'Tadó', 'Condoto', 'Bagadó'],
  'Córdoba': ['Montería', 'Cereté', 'Lorica', 'Sahagún', 'Montelíbano', 'Tierralta'],
  'Cundinamarca': ['Bogotá D.C.', 'Soacha', 'Zipaquirá', 'Facatativá', 'Chía', 'Mosquera', 'Funza', 'Madrid', 'Fusagasugá', 'Girardot', 'Cajicá'],
  'Guainía': ['Inírida'],
  'Guaviare': ['San José del Guaviare', 'Calamar'],
  'Huila': ['Neiva', 'Pitalito', 'Garzón', 'La Plata', 'Campoalegre'],
  'La Guajira': ['Riohacha', 'Maicao', 'Uribia', 'Manaure', 'Fonseca'],
  'Magdalena': ['Santa Marta', 'Ciénaga', 'Fundación', 'El Banco', 'Plato'],
  'Meta': ['Villavicencio', 'Acacías', 'Granada', 'San Martín', 'Puerto López'],
  'Nariño': ['Pasto', 'Tumaco', 'Ipiales', 'Túquerres', 'La Unión'],
  'Norte de Santander': ['Cúcuta', 'Ocaña', 'Pamplona', 'Villa del Rosario', 'Los Patios', 'Tibú'],
  'Putumayo': ['Mocoa', 'Puerto Asís', 'Orito', 'Valle del Guamuez', 'Sibundoy'],
  'Quindío': ['Armenia', 'Calarcá', 'Montenegro', 'Quimbaya', 'La Tebaida'],
  'Risaralda': ['Pereira', 'Dosquebradas', 'Santa Rosa de Cabal', 'La Virginia', 'Quinchía'],
  'San Andrés': ['San Andrés', 'Providencia'],
  'Santander': ['Bucaramanga', 'Floridablanca', 'Girón', 'Piedecuesta', 'Barrancabermeja', 'San Gil', 'Socorro'],
  'Sucre': ['Sincelejo', 'Corozal', 'Sampués', 'San Marcos', 'Tolú'],
  'Tolima': ['Ibagué', 'Espinal', 'Girardot', 'Honda', 'Melgar', 'Chaparral'],
  'Valle del Cauca': ['Cali', 'Buenaventura', 'Palmira', 'Tuluá', 'Buga', 'Cartago', 'Jamundí', 'Yumbo', 'Florida', 'Pradera'],
  'Vaupés': ['Mitú'],
  'Vichada': ['Puerto Carreño'],
};

const departamentos = Object.keys(colombiaDatos).sort();
const ciudadesDelDepartamento = computed(() => formNueva.departamento ? colombiaDatos[formNueva.departamento] ?? [] : []);
const ciudadesEditar = computed(() => formEditar.departamento ? colombiaDatos[formEditar.departamento] ?? [] : []);

const rulesNueva: FormRules = {
  nit: [{ required: true, message: 'Obligatorio', trigger: 'blur' }],
  nombre: [{ required: true, message: 'Obligatorio', trigger: 'blur' }],
  email: [{ required: true, message: 'Obligatorio', trigger: 'blur' }, { type: 'email', message: 'Correo inválido', trigger: 'blur' }],
  email_confirmacion: [
    { required: true, message: 'Obligatorio', trigger: 'blur' },
    { type: 'email', message: 'Correo inválido', trigger: 'blur' },
    {
      validator: (_rule: any, value: string, callback: any) => {
        if (value && value !== formNueva.email) callback(new Error('Los correos no coinciden'));
        else callback();
      },
      trigger: 'blur',
    },
  ],
  telefono: [{ required: true, message: 'Obligatorio', trigger: 'blur' }],
  direccion: [{ required: true, message: 'Obligatorio', trigger: 'blur' }],
  departamento: [{ required: true, message: 'Obligatorio', trigger: 'change' }],
  ciudad: [{ required: true, message: 'Obligatorio', trigger: 'change' }],
  representante_legal: [{ required: true, message: 'Obligatorio', trigger: 'blur' }],
  cedula_representante: [{ required: true, message: 'Obligatorio', trigger: 'blur' }],
};
// El filtro persiste por usuario — con llave compartida se le aplicaba a
// otras cuentas en el mismo PC.
const filtroBuscar = useStorage(`clinica-buscar-u${useAuthStore().user?.id ?? 0}`, '');
const filtroCiudad = ref('');
const filtroBuscarDebounced = ref(filtroBuscar.value);
const updateDebounced = useDebounceFn((val: string) => { filtroBuscarDebounced.value = val; }, 300);
watch(filtroBuscar, (val) => updateDebounced(val));

const ciudadesUnicas = computed(() =>
  [...new Set(clinicas.value.map(c => c.ciudad).filter((c): c is string => !!c))].sort(),
);

/* ── Estadísticas por ciudad (panel bajo el mapa) ── */
const CIUDAD_COLORS = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#14b8a6', '#f43f5e', '#6366f1', '#0ea5e9'];
const estadisticasCiudad = computed(() => {
  const mapa = new Map<string, number>();
  clinicas.value.forEach(c => {
    if (c.ciudad) mapa.set(c.ciudad, (mapa.get(c.ciudad) ?? 0) + 1);
  });
  const lista = Array.from(mapa.entries())
    .map(([ciudad, total]) => ({ ciudad, total }))
    .sort((a, b) => b.total - a.total);
  return { lista, max: lista[0]?.total ?? 1 };
});

const hayFiltros = computed(() => !!filtroBuscar.value.trim() || !!filtroCiudad.value);

/* ── Barra de filtros inline (botón "Filtros") — queda dentro del card,
   empuja la tabla hacia abajo en vez de montarse encima. Se cierra solo
   con el mismo botón. ── */
const filtrosAbiertos = ref(false);
const filtrosWrap = ref<HTMLElement | null>(null);
function toggleFiltros() {
  filtrosAbiertos.value = !filtrosAbiertos.value;
}
const conteoFiltros = computed(() =>
  (filtroCiudad.value ? 1 : 0) + (filtroBuscar.value.trim() ? 1 : 0),
);

function limpiarFiltros() {
  filtroBuscar.value = '';
  filtroCiudad.value = '';
}

const conReferencias = computed(() => clinicas.value.filter(c => (c.solicitudes_count ?? 0) > 0));

const nuevasEsteMes = computed(() => {
  const mes = new Date().toISOString().slice(0, 7);
  return clinicas.value.filter(c => c.created_at?.startsWith(mes));
});

/** Densidad de registros suavizada: cada alta se reparte en buckets vecinos
 * (picos curvos en vez de escalones) y se suma una ondulación sutil
 * determinística para que la línea tenga ritmo como la de Solicitudes. */
function serieCrecimiento(lista: Clinica[]): number[] {
  const fechas = lista
    .map(c => c.created_at?.slice(0, 10))
    .filter((f): f is string => !!f)
    .sort();
  if (!fechas.length) return [];

  const desde = new Date(fechas[0]);
  const hasta = new Date();
  const dias = Math.max(1, Math.round((hasta.getTime() - desde.getTime()) / 86_400_000));
  const puntos = 18;
  const densidad = Array(puntos).fill(0) as number[];

  fechas.forEach(f => {
    const pos = ((new Date(f).getTime() - desde.getTime()) / 86_400_000 / dias) * (puntos - 1);
    for (let k = -2; k <= 2; k++) {
      const idx = Math.round(pos) + k;
      if (idx >= 0 && idx < puntos) densidad[idx]! += Math.exp(-(k * k) / 1.4);
    }
  });

  const max = Math.max(...densidad, 1);
  const seed = lista.length % 7;
  return densidad.map((v, i) =>
    v
    + max * 0.2 * (0.5 + 0.5 * Math.sin(i * 1.9 + seed))
    + max * 0.07 * Math.sin(i * 4.3 + seed * 2),
  );
}

const crecimientoClinicas = computed(() => serieCrecimiento(clinicas.value));
const serieConReferencias = computed(() => serieCrecimiento(conReferencias.value));
const serieNuevasMes = computed(() => serieCrecimiento(nuevasEsteMes.value));
const serieCiudades = computed(() => serieCrecimiento(clinicas.value.filter(c => c.ciudad)));

const deltaTotal = computed(() => {
  const hoy = new Date();
  const mes = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
  const nuevas = clinicas.value.filter(c => c.created_at?.startsWith(mes)).length;
  const antes = clinicas.value.length - nuevas;
  if (!nuevas) return '= Sin altas este mes';
  if (!antes) return '↑ Nuevas este mes';
  return `↑${Math.round((nuevas / antes) * 100)}% este mes`;
});
const deltaConReferencias = computed(() => `= ${clinicas.value.length ? Math.round((conReferencias.value.length / clinicas.value.length) * 100) : 0}% del total`);


const AVATAR_COLORES = [
  { bg: 'linear-gradient(135deg, #dbeafe, #bfdbfe)', fg: '#1d4ed8', bd: '#bfdbfe' },
  { bg: 'linear-gradient(135deg, #ede9fe, #ddd6fe)', fg: '#6d28d9', bd: '#ddd6fe' },
  { bg: 'linear-gradient(135deg, #d1fae5, #a7f3d0)', fg: '#047857', bd: '#a7f3d0' },
  { bg: 'linear-gradient(135deg, #fef3c7, #fde68a)', fg: '#b45309', bd: '#fde68a' },
  { bg: 'linear-gradient(135deg, #fce7f3, #fbcfe8)', fg: '#be185d', bd: '#fbcfe8' },
  { bg: 'linear-gradient(135deg, #cffafe, #a5f3fc)', fg: '#0e7490', bd: '#a5f3fc' },
  { bg: 'linear-gradient(135deg, #ffedd5, #fed7aa)', fg: '#c2410c', bd: '#fed7aa' },
  { bg: 'linear-gradient(135deg, #e0e7ff, #c7d2fe)', fg: '#4338ca', bd: '#c7d2fe' },
];

function avatarStyle(nombre = '') {
  let h = 0;
  for (let i = 0; i < nombre.length; i++) h = (h * 31 + nombre.charCodeAt(i)) >>> 0;
  const c = AVATAR_COLORES[h % AVATAR_COLORES.length];
  return { background: c.bg, color: c.fg, border: `1px solid ${c.bd}` };
}

const clinicasFiltradas = computed(() => {
  let result = clinicas.value;
  if (filtroCiudad.value) {
    result = result.filter(c => c.ciudad === filtroCiudad.value);
  }
  if (filtroBuscarDebounced.value.trim()) {
    const q = filtroBuscarDebounced.value.toLowerCase().trim();
    result = result.filter(c =>
      (c.nit ?? '').toLowerCase().includes(q) ||
      (c.ciudad ?? '').toLowerCase().includes(q) ||
      c.nombre.toLowerCase().includes(q)
    );
  }
  const dir = sortDir.value === 'asc' ? 1 : -1;
  result = [...result].sort((a, b) => {
    const va = (a[sortKey.value] ?? '').toString().toLowerCase();
    const vb = (b[sortKey.value] ?? '').toString().toLowerCase();
    return va < vb ? -dir : va > vb ? dir : 0;
  });
  return result;
});

const totalPaginas = computed(() => Math.max(1, Math.ceil(clinicasFiltradas.value.length / itemsPorPagina.value)));
const clinicasPaginadas = computed(() => {
  const start = (paginaActual.value - 1) * itemsPorPagina.value;
  return clinicasFiltradas.value.slice(start, start + itemsPorPagina.value);
});

const paginasVisibles = computed<(number | '…')[]>(() => {
  const t = totalPaginas.value;
  const p = paginaActual.value;
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1);
  if (p <= 4) return [1, 2, 3, 4, 5, '…', t];
  if (p >= t - 3) return [1, '…', t - 4, t - 3, t - 2, t - 1, t];
  return [1, '…', p - 1, p, p + 1, '…', t];
});

/* ── Última actividad: la más reciente entre solicitudes y sesiones ── */
const MESES_CORTO = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

function ultimaActividad(c: Clinica): string {
  const candidatas = [c.solicitudes_max_created_at, c.sessions_max_created_at, c.created_at]
    .filter((f): f is string => !!f)
    .sort();
  return candidatas[candidatas.length - 1] ?? '';
}

function formatFechaCorta(fechaIso: string): string {
  if (!fechaIso) return '—';
  const [y, m, d] = fechaIso.slice(0, 10).split('-');
  if (!y || !m || !d) return fechaIso;
  return `${Number(d)} ${MESES_CORTO[Number(m) - 1]} ${y}`;
}

function actividadClase(c: Clinica): string {
  const f = ultimaActividad(c);
  if (!f) return 'clinic-act-dot--old';
  const dias = (Date.now() - new Date(f).getTime()) / 86_400_000;
  if (dias <= 7) return 'clinic-act-dot--ok';
  if (dias <= 30) return 'clinic-act-dot--warn';
  return 'clinic-act-dot--old';
}

// Selección en el mapa — solo controla el foco/vista, no acciones masivas
const seleccionadas = ref<Set<number>>(new Set());

const todasSeleccionadas = computed(() => {
  return clinicasPaginadas.value.length > 0 && clinicasPaginadas.value.every(c => seleccionadas.value.has(c.id));
});

function toggleSeleccion(id: number) {
  const s = new Set(seleccionadas.value);
  if (s.has(id)) {
    s.delete(id);
    mapaRef.value?.restablecerVista();
  } else {
    s.add(id);
    const clinica = clinicas.value.find(c => c.id === id);
    if (clinica) mapaRef.value?.enfocarClinica(clinica);
  }
  seleccionadas.value = s;
}

function toggleSeleccionTodas() {
  const s = new Set(seleccionadas.value);
  if (todasSeleccionadas.value) {
    clinicasPaginadas.value.forEach(c => s.delete(c.id));
    mapaRef.value?.restablecerVista();
  } else {
    clinicasPaginadas.value.forEach(c => s.add(c.id));
  }
  seleccionadas.value = s;
}

function iniciales(nombre: string) {
  const palabras = nombre.trim().split(/\s+/);
  if (palabras.length === 0) return '?';
  if (palabras.length === 1) return palabras[0].slice(0, 2).toUpperCase();
  return (palabras[0][0] + palabras[1][0]).toUpperCase();
}

function exportarExcel() {
  const rows = clinicasFiltradas.value;
  const headers = ['NIT', 'Nombre', 'Email', 'Teléfono', 'Ciudad', 'Departamento', 'Dirección', 'Persona a cargo', 'Cédula', 'Fecha registro'];
  const csv = [
    headers.join('\t'),
    ...rows.map(c => [
      c.nit, c.nombre, c.email, c.telefono ?? '', c.ciudad ?? '', c.departamento ?? '',
      c.direccion ?? '', c.representante_legal ?? '', c.cedula_representante ?? '',
      formatFecha(c.created_at),
    ].map(v => `"${String(v).replace(/"/g, '""')}"`).join('\t')),
  ].join('\n');
  const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `clinicas_${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
  notify.success(`Exportadas ${rows.length} clínicas`);
}

function abrirEditar(clinica: Clinica) {
  Object.assign(formEditar, {
    id: clinica.id,
    nit: clinica.nit ?? '',
    nombre: clinica.nombre,
    email: clinica.email,
    telefono: clinica.telefono ?? '',
    departamento: clinica.departamento ?? '',
    ciudad: clinica.ciudad ?? '',
    direccion: clinica.direccion ?? '',
    representante_legal: clinica.representante_legal ?? '',
    cedula_representante: clinica.cedula_representante ?? '',
  });
  modalEditar.value = true;
}

async function guardarEdicion() {
  if (!formEditarRef.value) return;
  const esValido = await formEditarRef.value.validate().catch(() => false);
  if (!esValido) return;
  try {
    procesandoEditar.value = true;
    await http.put(`/api/clinicas/${formEditar.id}`, {
      nit: formEditar.nit?.trim() || null,
      nombre: formEditar.nombre,
      razon_social: formEditar.nombre,
      email: formEditar.email,
      telefono: formEditar.telefono,
      ciudad: formEditar.ciudad,
      departamento: formEditar.departamento,
      direccion: formEditar.direccion,
      representante_legal: formEditar.representante_legal,
      cedula_representante: formEditar.cedula_representante,
    });
    notify.success('Clínica actualizada correctamente');
    modalEditar.value = false;
    await cargar();
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? 'Error al actualizar la clínica';
    notify.error(msg);
  } finally {
    procesandoEditar.value = false;
  }
}

const displayStats = ref([0, 0, 0, 0]);

function animateCounters(objetivos: number[]) {
  const inicio = performance.now();
  const desde = [...displayStats.value];
  const dur = 700;
  const tick = (t: number) => {
    const k = Math.min(1, (t - inicio) / dur);
    const e = 1 - Math.pow(1 - k, 3);
    displayStats.value = objetivos.map((o, i) => Math.round(desde[i] + (o - desde[i]) * e));
    if (k < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

async function cargar() {
  try {
    cargando.value = true;
    const { data } = await http.get('/api/clinicas');
    clinicas.value = data.data;
    animateCounters([
      data.data.length,
      conReferencias.value.length,
      nuevasEsteMes.value.length,
      ciudadesUnicas.value.length,
    ]);
  } catch {
    notify.error('Error al cargar las clínicas');
  } finally {
    cargando.value = false;
  }
}

/** Refresco automático de fondo: invisible — sin esqueleto de carga y
 * solo actualiza los datos cuando algo realmente cambió. */
async function cargarSilencioso() {
  try {
    const { data } = await http.get('/api/clinicas');
    actualizarSiCambio(clinicas, data.data);
  } catch {
    // Refresco de fondo: si falla, se reintenta en el siguiente ciclo sin interrumpir al usuario.
  }
}

function verDetalle(clinica: Clinica) {
  clinicaSeleccionada.value = clinica;
  modalDetalle.value = true;
}

function manejarAccionFila(comando: string, clinica: Clinica) {
  const acciones: Record<string, (c: Clinica) => void> = {
    editar: abrirEditar,
  };
  acciones[comando]?.(clinica);
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

function abrirCargaMasiva() {
  textoPegado.value = '';
  filasCarga.value = [];
  modalCargaMasiva.value = true;
}

function abrirNuevaClinica() {
  Object.assign(formNueva, { nit: '', nombre: '', email: '', email_confirmacion: '', telefono: '', departamento: '', ciudad: '', direccion: '', representante_legal: '', cedula_representante: '' });
  modalNuevaClinica.value = true;
}

async function guardarNuevaClinica() {
  if (!formNuevaRef.value) return;
  const esValido = await formNuevaRef.value.validate().catch(() => false);
  if (!esValido) return;
  try {
    procesandoNueva.value = true;
    await http.post('/api/clinicas', {
      nit: formNueva.nit,
      nombre: formNueva.nombre,
      razon_social: formNueva.nombre,
      email: formNueva.email,
      telefono: formNueva.telefono,
      ciudad: formNueva.ciudad,
      departamento: formNueva.departamento,
      direccion: formNueva.direccion,
      representante_legal: formNueva.representante_legal,
      cedula_representante: formNueva.cedula_representante,
    });
    notify.success('Clínica creada correctamente');
    modalNuevaClinica.value = false;
    await cargar();
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? 'Error al crear la clínica';
    notify.error(msg);
  } finally {
    procesandoNueva.value = false;
  }
}

function agregarFila() {
  filasCarga.value.push({ nit: '', nombre: '', email: '', telefono: '', ciudad: '', departamento: '', direccion: '', representante_legal: '', cedula_representante: '' });
}

function procesarPegado() {
  const lineas = textoPegado.value.trim().split(/\n/).filter(l => l.trim());
  const nuevas: typeof filasCarga.value = [];
  for (const linea of lineas) {
    const cols = linea.split(/\t/).map(c => c.trim());
    if (cols.length < 2) continue;
    nuevas.push({
      nit: cols[0] ?? '',
      nombre: cols[1] ?? '',
      email: cols[2] ?? '',
      telefono: cols[3] ?? '',
      ciudad: cols[4] ?? '',
      departamento: cols[5] ?? '',
      direccion: cols[6] ?? '',
      representante_legal: cols[7] ?? '',
      cedula_representante: cols[8] ?? '',
    });
  }
  if (nuevas.length === 0) {
    notify.warning('No se pudieron extraer datos. Asegúrate de copiar desde Excel con columnas separadas por tabulaciones.');
    return;
  }
  filasCarga.value = [...filasCarga.value, ...nuevas];
  textoPegado.value = '';
}

async function enviarCargaMasiva() {
  const validas = filasCarga.value.filter(f => f.nit.trim() && f.nombre.trim() && f.email.trim());
  if (validas.length === 0) {
    notify.warning('Debe haber al menos una fila con NIT, nombre y email');
    return;
  }
  try {
    procesandoCarga.value = true;
    const { data } = await http.post('/api/clinicas/carga-masiva', { clinicas: validas });
    notify.success(data.message);
    modalCargaMasiva.value = false;
    await cargar();
  } catch (e: any) {
    const msg = e?.response?.data?.message ?? 'Error al cargar las clínicas';
    notify.error(msg);
  } finally {
    procesandoCarga.value = false;
  }
}

onMounted(() => {
  cargar();
});

usePolling(() => {
  if (!cargando.value && !modalDetalle.value && !modalCargaMasiva.value && !modalNuevaClinica.value && !modalEditar.value) cargarSilencioso();
}, 30000);
</script>
