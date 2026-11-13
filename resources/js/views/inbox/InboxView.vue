<template>
  <div class="inbx-page h-full overflow-hidden p-2 sm:p-3">
    <div class="inbx-wrap">
      <header v-if="!panelNegar && !panelAceptar && !panelReenviar" class="inbx-head">
        <span class="inbx-head-icon"><component :is="InboxIcon" class="w-4 h-4" /></span>
        <h1>Bandeja de correo</h1>
        <small v-if="gmail.buzon">{{ gmail.buzon }}</small>
        <span v-if="noLeidos > 0" class="inbx-badge">{{ noLeidos }} sin leer</span>
        <div v-if="!panelNegar && !panelAceptar && !panelReenviar" class="inbx-head-actions">
          <div class="inbx-tabs">
            <button type="button" :class="{ 'inbx-tab-on': filtro === 'todos' }" @click="cambiarFiltro('todos')">Todos</button>
            <button type="button" :class="{ 'inbx-tab-on': filtro === 'no_leidos' }" @click="cambiarFiltro('no_leidos')">No leídos</button>
          </div>
          <el-select v-model="filtroCarpeta" size="small" placeholder="Carpeta" clearable class="inbx-carpeta-filtro" @change="cambiarCarpeta">
            <template #prefix><component :is="FolderIcon" class="w-3.5 h-3.5" /></template>
            <el-option label="Entrada (sin gestionar)" value="entrada" />
            <el-option v-for="c in carpetas" :key="c.nombre" :label="`${c.nombre} (${c.total})`" :value="c.nombre" />
          </el-select>
          <el-input
            v-model="buscar"
            size="small"
            placeholder="Asunto o remitente…"
            clearable
            class="inbx-search"
            @keyup.enter="cargar(true)"
            @clear="cargar(true)"
          />
          <button type="button" class="inbx-btn" :disabled="!gmail.configurado || sincronizando" title="Sincronizar con Gmail" @click="sincronizar">
            <component :is="RefreshIcon" class="w-4 h-4" :class="{ 'animate-spin': sincronizando }" />
            {{ sincronizando ? 'Sincronizando…' : 'Sincronizar' }}
          </button>
        </div>
      </header>

      <p v-if="!panelNegar && !panelAceptar && !panelReenviar && gmail.ultimo_error" class="inbx-alerta">Error de sincronización: {{ gmail.ultimo_error }}</p>
      <p v-else-if="!panelNegar && !panelAceptar && !panelReenviar && gmailCargado && !gmail.configurado" class="inbx-alerta">
        El buzón aún no está configurado — define el correo y sus credenciales en Configuración → Correo.
      </p>
      <p v-else-if="!panelNegar && !panelAceptar && !panelReenviar && gmail.ultima_sync" class="inbx-sync-info">Última sincronización: {{ fechaLarga(gmail.ultima_sync) }}</p>

      <div class="inbx-body" :class="{ 'inbx-body-negar': panelNegar || panelAceptar || panelReenviar }">
        <aside class="inbx-list">
          <p v-if="cargando && mensajes.length === 0" class="inbx-vacio">Cargando correos…</p>
          <p v-else-if="mensajes.length === 0" class="inbx-vacio">No hay correos{{ filtro === 'no_leidos' ? ' sin leer' : '' }}.</p>

          <button
            v-for="m in mensajes" :key="m.id"
            type="button"
            class="inbx-item"
            :class="{
              'inbx-item-on': seleccionado?.id === m.id,
              'inbx-item-new': !m.leido,
              'inbx-item-rad': m.gestion === 'radicada' || !!m.solicitud,
              'inbx-item-neg': m.gestion === 'negada',
            }"
            @click="abrir(m)"
          >
            <span class="inbx-mini">{{ nombreRemitente(m.remitente).charAt(0).toUpperCase() }}</span>
            <span class="inbx-item-top">
              <strong class="inbx-from">{{ nombreRemitente(m.remitente) }}</strong>
              <time>{{ fechaCorta(m.recibido_en) }}</time>
            </span>
            <span class="inbx-subject">{{ m.asunto }}</span>
            <span class="inbx-snippet">{{ m.snippet }}</span>
            <span class="inbx-item-meta">
              <span
                v-if="m.gestion === 'radicada' || m.solicitud"
                class="inbx-tag-rad"
                :title="`Radicada como solicitud #${m.solicitud?.id ?? '—'} por ${m.gestion_por || m.solicitud?.nombre_quien_responde || 'el equipo'}`"
              >
                <component :is="CheckCircleIcon" class="w-3 h-3" /> Radicada
              </span>
              <span
                v-else-if="m.gestion === 'negada'"
                class="inbx-tag-neg"
                :title="`Negada por ${m.gestion_por || 'el equipo'}${m.gestion_detalle ? ' — ' + m.gestion_detalle : ''}`"
              >
                <component :is="XCircleIcon" class="w-3 h-3" /> Negada
              </span>
              <component v-if="m.tiene_adjuntos" :is="ClipIcon" class="w-3 h-3" />
              <i v-if="!m.leido" class="inbx-dot" />
            </span>
          </button>

          <button v-if="meta.pagina < meta.paginas" type="button" class="inbx-mas" :disabled="cargando" @click="cargarMas">
            {{ cargando ? 'Cargando…' : 'Cargar más' }}
          </button>
        </aside>

        <section class="inbx-reader">
          <p v-if="!seleccionado" class="inbx-vacio">Selecciona un correo para leerlo.</p>
          <template v-else>
            <header class="inbx-reader-head">
              <h2>{{ seleccionado.asunto }}</h2>
              <p><strong>De:</strong> {{ seleccionado.remitente }}</p>
              <p><strong>Para:</strong> {{ seleccionado.para }}</p>
              <time>{{ fechaLarga(seleccionado.recibido_en) }}</time>
              <div v-if="seleccionado.adjuntos?.length" class="inbx-adjuntos">
                <span v-for="a in seleccionado.adjuntos" :key="a.nombre" class="inbx-adjunto">
                  <component :is="ClipIcon" class="w-3 h-3" /> {{ a.nombre }} <em>({{ tamano(a.tamano) }})</em>
                </span>
              </div>
            </header>
            <div v-if="seleccionado.gestion === 'negada'" class="inbx-rad inbx-rad--neg">
              <component :is="XCircleIcon" class="w-4.5 h-4.5 inbx-rad-ico" />
              <div class="inbx-rad-info">
                <strong>Solicitud negada desde este correo</strong>
                <span>por {{ seleccionado.gestion_por || 'el equipo' }}<template v-if="seleccionado.gestion_detalle"> · {{ seleccionado.gestion_detalle }}</template></span>
              </div>
            </div>
            <div v-else-if="seleccionado.gestion === 'reenviada'" class="inbx-rad inbx-rad--fwd">
              <component :is="ForwardIcon" class="w-4.5 h-4.5 inbx-rad-ico" />
              <div class="inbx-rad-info">
                <strong>Correo reenviado a otro destinatario</strong>
                <span>por {{ seleccionado.gestion_por || 'el equipo' }}<template v-if="seleccionado.gestion_detalle"> · {{ seleccionado.gestion_detalle }}</template></span>
              </div>
            </div>
            <div v-else-if="seleccionado.solicitud || seleccionado.gestion === 'radicada'" class="inbx-rad">
              <component :is="CheckCircleIcon" class="w-4.5 h-4.5 inbx-rad-ico" />
              <div class="inbx-rad-info">
                <strong>Solicitud{{ seleccionado.solicitud ? ' #' + seleccionado.solicitud.id : '' }} radicada desde este correo</strong>
                <span>
                  por {{ seleccionado.gestion_por || seleccionado.solicitud?.nombre_quien_responde || 'el equipo' }}
                  <template v-if="seleccionado.solicitud?.estado"> · <em class="inbx-rad-est">{{ seleccionado.solicitud.estado.replace('_', ' ') }}</em></template>
                </span>
              </div>
              <RouterLink v-if="seleccionado.solicitud" class="inbx-rad-link" :to="`/solicitudes-referencia?resaltar=${seleccionado.solicitud.id}`">
                Ver solicitud
              </RouterLink>
            </div>
            <div v-else-if="esCorreoSolicitud && !seleccionado.gestion" class="inbx-sol">
              <div class="inbx-sol-info">
                <span class="inbx-sol-ico"><component :is="ClipboardIcon" /></span>
                <div class="inbx-sol-txt">
                  <strong>Solo para referencias</strong>
                  <span>Gestiona esta solicitud de remisión desde aquí</span>
                </div>
              </div>
              <div class="inbx-sol-btns">
                <button type="button" class="inbx-sol-btn inbx-sol-ok" @click="abrirAceptar">
                  <span class="inbx-sol-btnico"><component :is="ClipboardIcon" /></span> Gestionar
                </button>
                <button type="button" class="inbx-sol-btn inbx-sol-no" @click="abrirNegar">
                  <span class="inbx-sol-btnico"><component :is="XIcon" /></span> Negar
                </button>
                <button type="button" class="inbx-sol-btn inbx-sol-fwd" @click="abrirReenviar">
                  <span class="inbx-sol-btnico"><component :is="ForwardIcon" /></span> Reenviar
                </button>
                <button type="button" class="inbx-sol-btn inbx-sol-del" :disabled="moviendo" @click="enviarPapelera">
                  <span class="inbx-sol-btnico"><component :is="TrashIcon" /></span> Eliminar
                </button>
              </div>
            </div>
            <div class="inbx-mover">
              <template v-if="seleccionado.gestion || seleccionado.solicitud">
                <component :is="FolderIcon" class="w-4 h-4" />
                <span>Carpeta</span>
                <el-select
                  :model-value="seleccionado.carpeta || ''"
                  size="small" filterable allow-create default-first-option
                  placeholder="Mover a una carpeta…"
                  class="inbx-mover-sel"
                  :disabled="moviendo"
                  @change="moverCarpeta"
                >
                  <el-option v-for="n in nombresCarpetas" :key="n" :label="n" :value="n" />
                </el-select>
              </template>
              <button
                v-if="!(esCorreoSolicitud && !seleccionado.gestion) && !seleccionado.gestion && !seleccionado.solicitud"
                type="button" class="inbx-trash inbx-fwd" :disabled="reenviando"
                title="Reenviar este correo a otro destinatario" @click="abrirReenviar"
              >
                <component :is="ForwardIcon" class="w-4 h-4" /> Reenviar
              </button>
              <button
                v-if="!(esCorreoSolicitud && !seleccionado.gestion)"
                type="button" class="inbx-trash" :disabled="moviendo"
                title="Eliminar definitivamente" @click="enviarPapelera"
              >
                <component :is="TrashIcon" class="w-4 h-4" /> Eliminar
              </button>
            </div>
            <p v-if="cargandoDetalle" class="inbx-vacio">Cargando mensaje…</p>
            <iframe
              v-else-if="seleccionado.cuerpo_html"
              class="inbx-html"
              sandbox=""
              :srcdoc="seleccionado.cuerpo_html"
              title="Contenido del correo"
            ></iframe>
            <pre v-else-if="seleccionado.cuerpo_texto" class="inbx-texto">{{ seleccionado.cuerpo_texto }}</pre>
            <p v-else class="inbx-vacio">Este correo no tiene contenido legible.</p>
          </template>
        </section>

        <aside v-if="panelNegar" class="inbx-negar">
          <header class="inbx-negar-head">
            <span class="inbx-negar-icon"><component :is="XCircleIcon" class="w-5 h-5" /></span>
            <div class="inbx-negar-head-info">
              <h3>Negar solicitud</h3>
              <p>Indica el motivo de la negación</p>
            </div>
            <button type="button" class="inbx-negar-close" @click="panelNegar = false">
              <component :is="XIcon" class="w-4 h-4" />
            </button>
          </header>

          <div class="inbx-negar-body">
            <div class="inbx-negar-paciente">
              <strong>{{ seleccionado?.asunto || 'Correo de solicitud' }}</strong>
              <span>{{ seleccionado?.remitente }}</span>
            </div>

            <div class="inbx-negar-grid3">
              <div>
                <label class="inbx-negar-label">Fecha del correo</label>
                <el-input :model-value="fechaNegar" disabled />
              </div>
              <div>
                <label class="inbx-negar-label">Hora del correo</label>
                <el-input :model-value="horaNegar" disabled />
              </div>
              <div>
                <label class="inbx-negar-label">Hora de negación</label>
                <el-input :model-value="horaNegacion" disabled />
              </div>
            </div>

            <div>
              <label class="inbx-negar-label">Quien responde</label>
              <el-input :model-value="authStore.user?.full_name ?? ''" disabled />
            </div>

            <div>
              <label class="inbx-negar-label">Remitente</label>
              <el-input :model-value="seleccionado?.remitente_email || 'Sin correo del remitente'" disabled />
            </div>

            <div>
              <label class="inbx-negar-label">EPS / Aseguradora <span class="inbx-req">*</span></label>
              <el-select v-model="formNegar.eps" placeholder="Seleccione la EPS con su tipo de contrato" filterable class="w-full" popper-class="negar-motivo-popper">
                <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
              </el-select>
            </div>

            <div>
              <label class="inbx-negar-label">Grupo de negación <span class="inbx-req">*</span></label>
              <div class="negar-grupo-btns">
                <button
                  type="button"
                  class="negar-grupo-btn"
                  :class="{ active: formNegar.grupo_negacion === 'no_red' }"
                  @click="formNegar.grupo_negacion = 'no_red'; formNegar.motivo_negacion = ''"
                >
                  <component :is="UserXIcon" class="w-4 h-4" />
                  <span>Paciente no RED<small>Motivo libre</small></span>
                </button>
                <button
                  type="button"
                  class="negar-grupo-btn"
                  :class="{ active: formNegar.grupo_negacion === 'poblacion_objeto' }"
                  @click="formNegar.grupo_negacion = 'poblacion_objeto'; formNegar.motivo_negacion = ''"
                >
                  <component :is="UsersIcon" class="w-4 h-4" />
                  <span>Población objeto<small>Lista de motivos</small></span>
                </button>
              </div>
            </div>

            <div v-if="formNegar.grupo_negacion === 'poblacion_objeto'">
              <label class="inbx-negar-label">Motivo de negación <span class="inbx-req">*</span></label>
              <el-select v-model="formNegar.motivo_negacion" placeholder="Seleccione el motivo" class="w-full" popper-class="negar-motivo-popper">
                <el-option v-for="m in MOTIVOS_NEGACION_POBLACION" :key="m" :label="m" :value="m" />
              </el-select>
            </div>
            <div v-else-if="formNegar.grupo_negacion === 'no_red'">
              <label class="inbx-negar-label">Motivo de negación <span class="inbx-req">*</span></label>
              <el-input v-model="formNegar.motivo_negacion" type="textarea" :rows="3" placeholder="Indique el motivo..." />
            </div>
            <p v-else class="negar-grupo-hint">Selecciona el grupo para habilitar el motivo de negación.</p>

            <div>
              <label class="inbx-negar-label">Observaciones adicionales</label>
              <el-input v-model="formNegar.observaciones" type="textarea" :rows="2" placeholder="Opcional" />
            </div>

            <div>
              <label class="inbx-negar-label">Correo de notificación a la clínica</label>
              <span class="inbx-negar-correo">{{ seleccionado?.remitente_email || 'Sin correo del remitente' }}</span>
            </div>

            <div>
              <label class="inbx-negar-label">Correos adicionales a notificar</label>
              <el-select v-model="formNegar.correos_extra" multiple filterable allow-create default-first-option :reserve-keyword="false" collapse-tags :max-collapse-tags="2" placeholder="Escribe correos y presiona Enter (opcional)" class="w-full" />
            </div>

            <div>
              <label class="inbx-negar-label"><component :is="FolderIcon" class="w-3.5 h-3.5" /> Guardar el correo en la carpeta</label>
              <el-select v-model="carpetaNegar" filterable allow-create default-first-option placeholder="Elige o escribe una carpeta" class="w-full" popper-class="negar-motivo-popper">
                <el-option v-for="n in nombresCarpetas" :key="n" :label="n" :value="n" />
              </el-select>
            </div>
          </div>


          <footer class="inbx-negar-foot">
            <button type="button" class="inbx-negar-cancel" :disabled="negando" @click="panelNegar = false">Cancelar</button>
            <button type="button" class="inbx-negar-confirm" :disabled="negando" @click="confirmarNegacion">
              <component :is="negando ? RefreshIcon : XIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': negando }" />
              {{ negando ? 'Negando…' : 'Confirmar negación' }}
            </button>
          </footer>
        </aside>

        <aside v-if="panelAceptar" class="inbx-negar inbx-aceptar">
          <header class="inbx-negar-head">
            <span class="inbx-negar-icon inbx-aceptar-icon"><component :is="CheckCircleIcon" class="w-5 h-5" /></span>
            <div class="inbx-negar-head-info">
              <h3>Nueva solicitud de referencia</h3>
              <p>Diligencia los datos del paciente y la remisión</p>
            </div>
            <button type="button" class="inbx-negar-close" @click="panelAceptar = false">
              <component :is="XIcon" class="w-4 h-4" />
            </button>
          </header>

          <div class="inbx-negar-body">
            <div class="inbx-negar-paciente">
              <strong>{{ seleccionado?.asunto || 'Correo de solicitud' }}</strong>
              <span>{{ seleccionado?.remitente }}</span>
            </div>

            <p v-if="prellenados.length" class="inbx-auto">
              <component :is="CheckIcon" class="w-3.5 h-3.5" />
              Pre-llenado desde el correo: {{ prellenados.join(', ') }} — revísalos antes de registrar.
            </p>

            <p class="inbx-form-seccion">Datos del paciente</p>

            <div class="inbx-negar-grid2">
              <div>
                <label class="inbx-negar-label">Tipo de documento <span class="inbx-req">*</span></label>
                <el-select v-model="formSol.tipo_documento" placeholder="Tipo" class="w-full" popper-class="negar-motivo-popper">
                  <el-option v-for="t in TIPOS_DOCUMENTO" :key="t" :label="t" :value="t" />
                </el-select>
              </div>
              <div>
                <label class="inbx-negar-label">Número de documento <span class="inbx-req">*</span></label>
                <el-input v-model="formSol.numero_documento" />
              </div>
              <div>
                <label class="inbx-negar-label">Edad <span class="inbx-req">*</span></label>
                <el-input v-model="formSol.edad" placeholder="Ej: 55" maxlength="3" />
              </div>
              <div>
                <label class="inbx-negar-label">Género <span class="inbx-req">*</span></label>
                <div class="inbx-toggle">
                  <button type="button" :class="{ active: formSol.genero === 'M' }" @click="formSol.genero = 'M'">Masculino</button>
                  <button type="button" :class="{ active: formSol.genero === 'F' }" @click="formSol.genero = 'F'">Femenino</button>
                </div>
              </div>
              <div>
                <label class="inbx-negar-label">EPS / Aseguradora <span class="inbx-req">*</span></label>
                <el-select v-model="formSol.eps" placeholder="Seleccione la EPS" filterable class="w-full" popper-class="negar-motivo-popper">
                  <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
                </el-select>
              </div>
              <div>
                <label class="inbx-negar-label">Teléfono del paciente</label>
                <el-input v-model="formSol.telefono_paciente" maxlength="15" />
              </div>
              <div>
                <label class="inbx-negar-label">Nombres <span class="inbx-req">*</span></label>
                <el-input v-model="formSol.nombres" placeholder="Nombres del paciente" />
              </div>
              <div>
                <label class="inbx-negar-label">Apellidos <span class="inbx-req">*</span></label>
                <el-input v-model="formSol.apellidos" placeholder="Apellidos del paciente" />
              </div>
              <div>
                <label class="inbx-negar-label">Municipio</label>
                <el-input v-model="formSol.municipio" placeholder="Municipio (opcional)" />
              </div>
            </div>

            <div v-if="duplicadas.length" class="inbx-dup">
              <component :is="AlertIcon" class="w-4 h-4 inbx-dup-ico" />
              <div class="inbx-dup-info">
                <strong>Posible duplicado: este documento ya tiene {{ duplicadas.length === 1 ? 'una solicitud activa' : 'solicitudes activas' }}</strong>
                <span v-for="d in duplicadas" :key="d.id">
                  #{{ d.id }} · {{ d.paciente }}<template v-if="d.clinica"> · {{ d.clinica }}</template> · {{ d.estado.replace('_', ' ') }}
                  — <button type="button" class="inbx-dup-ver" @click="verDuplicada(d.id)">ver</button>
                </span>
              </div>
            </div>

            <div>
              <label class="inbx-negar-label">Dirección del paciente</label>
              <el-input v-model="formSol.direccion" maxlength="25" />
            </div>

            <div>
              <label class="inbx-negar-label">Urgencia vital <span class="inbx-req">*</span></label>
              <div class="inbx-toggle">
                <button type="button" :class="{ active: formSol.urgencia_vital === false }" @click="formSol.urgencia_vital = false">No</button>
                <button type="button" :class="{ active: formSol.urgencia_vital === true }" @click="formSol.urgencia_vital = true">Sí</button>
              </div>
            </div>

            <div>
              <label class="inbx-negar-label">Historia clínica <span class="inbx-req">*</span></label>
              <el-input v-model="formSol.historia_clinica" type="textarea" :rows="3" placeholder="Motivo de remisión, antecedentes, estado actual del paciente..." />
            </div>

            <div>
              <label class="inbx-negar-label">Diagnóstico principal <span class="inbx-req">*</span></label>
              <el-input v-model="formSol.diagnostico" placeholder="Ej: ULCERA DEL MIEMBRO INFERIOR - L97X" />
            </div>

            <p class="inbx-form-seccion">Datos de la remisión</p>

            <div class="inbx-negar-grid2">
              <div>
                <label class="inbx-negar-label">Especialidad requerida <span class="inbx-req">*</span></label>
                <el-select v-model="formSol.especialidad" filterable placeholder="Seleccione" class="w-full" popper-class="negar-motivo-popper">
                  <el-option v-for="e in ESPECIALIDADES" :key="e" :label="e" :value="e" />
                </el-select>
              </div>
              <div>
                <label class="inbx-negar-label">Tipo de referencia <span class="inbx-req">*</span></label>
                <el-select v-model="formSol.tipo_referencia" placeholder="Seleccione" class="w-full" popper-class="negar-motivo-popper">
                  <el-option label="Referencia redonda" value="Referencia redonda" />
                  <el-option label="Referencia integral" value="Referencia integral" />
                </el-select>
              </div>
              <div>
                <label class="inbx-negar-label">Servicio / Ubicación actual <span class="inbx-req">*</span></label>
                <el-select v-model="formSol.servicio" placeholder="Seleccione" class="w-full" popper-class="negar-motivo-popper">
                  <el-option v-for="s in SERVICIOS" :key="s" :label="s" :value="s" />
                </el-select>
              </div>
              <div>
                <label class="inbx-negar-label">Condición especial</label>
                <el-select v-model="formSol.condicion_especial" placeholder="Seleccione" clearable class="w-full" popper-class="negar-motivo-popper">
                  <el-option label="Discapacidad visual" value="Discapacidad visual" />
                  <el-option label="Discapacidad auditiva" value="Discapacidad auditiva" />
                  <el-option label="Discapacidad mental" value="Discapacidad mental" />
                  <el-option label="Discapacidad física" value="Discapacidad física" />
                </el-select>
              </div>
              <div>
                <label class="inbx-negar-label">¿Requiere aislamiento?</label>
                <div class="inbx-toggle">
                  <button type="button" :class="{ active: formSol.aislamiento === true }" @click="formSol.aislamiento = true">Sí</button>
                  <button type="button" :class="{ active: formSol.aislamiento === false }" @click="formSol.aislamiento = false">No</button>
                </div>
              </div>
              <div v-if="formSol.aislamiento === true">
                <label class="inbx-negar-label">Tipo de aislamiento</label>
                <el-select v-model="formSol.tipo_aislamiento" placeholder="Seleccione" class="w-full" popper-class="negar-motivo-popper">
                  <el-option label="Contacto" value="contacto" />
                  <el-option label="Respiratorio" value="respiratorio" />
                  <el-option label="Cohortizado" value="cohortizado" />
                  <el-option label="Vectores" value="vectores" />
                </el-select>
              </div>
              <div>
                <label class="inbx-negar-label">¿Paciente gestante?</label>
                <div class="inbx-toggle">
                  <button type="button" :class="{ active: formSol.gestante === true }" @click="formSol.gestante = true">Sí</button>
                  <button type="button" :class="{ active: formSol.gestante === false }" @click="formSol.gestante = false">No</button>
                </div>
              </div>
            </div>
          </div>

          <p v-if="seleccionado?.adjuntos?.length" class="inbx-adjuntos-aviso">
            <component :is="ClipIcon" class="w-3.5 h-3.5" />
            Este correo trae {{ seleccionado.adjuntos.length }} adjunto{{ seleccionado.adjuntos.length === 1 ? '' : 's' }} — se anexarán a la solicitud.
          </p>
          <div class="inbx-carpeta-sel">
            <label class="inbx-negar-label"><component :is="FolderIcon" class="w-3.5 h-3.5" /> Guardar el correo en la carpeta</label>
            <el-select v-model="carpetaAceptar" filterable allow-create default-first-option placeholder="Elige o escribe una carpeta" class="w-full" popper-class="negar-motivo-popper">
              <el-option v-for="n in nombresCarpetas" :key="n" :label="n" :value="n" />
            </el-select>
          </div>
          <footer class="inbx-negar-foot">
            <button type="button" class="inbx-negar-cancel" :disabled="registrando" @click="panelAceptar = false">Cancelar</button>
            <button type="button" class="inbx-negar-confirm inbx-aceptar-confirm" :disabled="registrando" @click="confirmarAceptacion">
              <component :is="registrando ? RefreshIcon : CheckIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': registrando }" />
              {{ registrando ? 'Registrando…' : 'Registrar solicitud' }}
            </button>
          </footer>
        </aside>

        <aside v-if="panelReenviar" class="inbx-negar">
          <header class="inbx-negar-head">
            <span class="inbx-negar-icon inbx-fwd-icon"><component :is="ForwardIcon" class="w-5 h-5" /></span>
            <div class="inbx-negar-head-info">
              <h3>Reenviar correo</h3>
              <p>El correo y sus adjuntos salen al destinatario que indiques</p>
            </div>
            <button type="button" class="inbx-negar-close" @click="panelReenviar = false">
              <component :is="XIcon" class="w-4 h-4" />
            </button>
          </header>

          <div class="inbx-negar-body">
            <div class="inbx-negar-paciente">
              <strong>{{ seleccionado?.asunto || '(sin asunto)' }}</strong>
              <span>{{ seleccionado?.remitente }}</span>
            </div>

            <div>
              <label class="inbx-negar-label">Enviar a (correo del destinatario) <span class="inbx-req">*</span></label>
              <el-select v-model="formReenviar.para" filterable allow-create default-first-option placeholder="Elige una clínica o escribe el correo" class="w-full" popper-class="negar-motivo-popper">
                <el-option v-for="c in correosClinicas" :key="c.email" :label="`${c.nombre} — ${c.email}`" :value="c.email" />
              </el-select>
            </div>

            <div>
              <label class="inbx-negar-label">Nota para el destinatario</label>
              <el-input v-model="formReenviar.nota" type="textarea" :rows="3" placeholder="Opcional — va arriba del correo original" />
            </div>

            <label class="inbx-fwd-check">
              <input v-model="formReenviar.avisar_remitente" type="checkbox" />
              <span>Avisar a <strong>{{ seleccionado?.remitente_email || 'el remitente' }}</strong> que su correo fue redirigido</span>
            </label>

            <p v-if="seleccionado?.tiene_adjuntos" class="inbx-fwd-adj">
              <component :is="ClipIcon" class="w-3.5 h-3.5" /> Se reenvían los {{ seleccionado.adjuntos?.length || '' }} adjuntos del correo original.
            </p>

            <div class="inbx-carpeta-sel">
              <label class="inbx-negar-label"><component :is="FolderIcon" class="w-3.5 h-3.5" /> Guardar el correo en la carpeta</label>
              <el-select v-model="carpetaReenviar" filterable allow-create default-first-option placeholder="Elige o escribe una carpeta" class="w-full" popper-class="negar-motivo-popper">
                <el-option v-for="n in nombresCarpetas" :key="n" :label="n" :value="n" />
              </el-select>
            </div>
          </div>

          <footer class="inbx-negar-foot">
            <button type="button" class="inbx-negar-cancel" :disabled="reenviando" @click="panelReenviar = false">Cancelar</button>
            <button type="button" class="inbx-negar-confirm inbx-fwd-confirm" :disabled="reenviando" @click="confirmarReenvio">
              <component :is="reenviando ? RefreshIcon : ForwardIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': reenviando }" />
              {{ reenviando ? 'Reenviando…' : 'Reenviar correo' }}
            </button>
          </footer>
        </aside>
      </div>
    </div>

    <!-- Detalle rápido de una posible solicitud duplicada: permite decidir si
         el correo es la misma remisión o un caso nuevo del mismo paciente. -->
    <div v-if="dupDetalle || dupCargando" class="inbx-dup-overlay" @click.self="dupDetalle = null">
      <div class="inbx-dup-modal">
        <header class="inbx-dup-modal-head">
          <div>
            <h3>Solicitud{{ dupDetalle ? ' #' + dupDetalle.id : '' }}</h3>
            <p>Esta solicitud ya existe con el mismo documento</p>
          </div>
          <button type="button" class="inbx-negar-close" @click="dupDetalle = null"><component :is="XIcon" class="w-4 h-4" /></button>
        </header>
        <p v-if="dupCargando" class="inbx-vacio">Cargando…</p>
        <template v-else-if="dupDetalle">
          <div class="inbx-dup-chips">
            <span class="inbx-dup-est" :data-e="dupDetalle.estado">{{ estadoLabel(dupDetalle.estado) }}</span>
            <span class="inbx-dup-origen">{{ dupDetalle.origen === 'correo' ? 'Correo electrónico' : 'Aplicativo' }}</span>
          </div>
          <dl class="inbx-dup-datos">
            <div><dt>Paciente</dt><dd>{{ nombreCompleto(dupDetalle) }}</dd></div>
            <div><dt>Documento</dt><dd>{{ dupDetalle.tipo_documento }} {{ dupDetalle.numero_documento }}</dd></div>
            <div><dt>Clínica</dt><dd>{{ dupDetalle.clinica?.nombre || '—' }}</dd></div>
            <div><dt>EPS</dt><dd>{{ dupDetalle.eps || '—' }}</dd></div>
            <div><dt>Especialidad</dt><dd>{{ dupDetalle.especialidad_requerida || '—' }}</dd></div>
            <div><dt>Servicio</dt><dd>{{ dupDetalle.servicio_remision || dupDetalle.servicio_ubicacion_actual || '—' }}</dd></div>
            <div><dt>Tipo de referencia</dt><dd>{{ dupDetalle.tipo_referencia || '—' }}</dd></div>
            <div><dt>Radicada</dt><dd>{{ fechaLarga(dupDetalle.created_at) }}</dd></div>
            <div v-if="dupDetalle.hora_respuesta"><dt>Respondida</dt><dd>{{ dupDetalle.hora_respuesta }}<template v-if="dupDetalle.nombre_quien_responde"> · {{ dupDetalle.nombre_quien_responde }}</template></dd></div>
            <div v-if="dupDetalle.motivo_negacion"><dt>Motivo de negación</dt><dd>{{ dupDetalle.motivo_negacion }}</dd></div>
            <div v-if="dupDetalle.codigo_aceptacion"><dt>Código de aceptación</dt><dd>{{ dupDetalle.codigo_aceptacion }}<template v-if="dupDetalle.lugar_llegada"> · {{ dupDetalle.lugar_llegada }}</template></dd></div>
          </dl>
          <footer class="inbx-dup-modal-foot">
            <RouterLink class="inbx-dup-abrir" :to="`/solicitudes-referencia?resaltar=${dupDetalle.id}`">Abrir en Solicitudes</RouterLink>
          </footer>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
  AlertTriangle as AlertIcon,
  Inbox as InboxIcon,
  Paperclip as ClipIcon,
  Folder as FolderIcon,
  RefreshCw as RefreshIcon,
  Check as CheckIcon,
  X as XIcon,
  XCircle as XCircleIcon,
  CheckCircle as CheckCircleIcon,
  ClipboardList as ClipboardIcon,
  Trash2 as TrashIcon,
  UserX as UserXIcon,
  Users as UsersIcon,
  Forward as ForwardIcon,
} from '@lucide/vue';
import { ElMessageBox } from 'element-plus';
import http from '@/plugins/axios';
import notify from '@/plugins/toast';
import { useAuthStore } from '@/stores/auth';
import { EPS_LIST, EPS_CON_CONTRATO, TIPOS_DOCUMENTO, ESPECIALIDADES, SERVICIOS } from '@/data/referencia';

interface Adjunto { nombre: string; tipo: string; tamano: number }

interface InboxItem {
  id: number;
  gmail_id: string;
  remitente: string | null;
  remitente_email: string | null;
  para: string | null;
  asunto: string | null;
  snippet: string | null;
  cuerpo_texto: string | null;
  cuerpo_html: string | null;
  tiene_adjuntos: boolean;
  adjuntos: Adjunto[] | null;
  recibido_en: string;
  leido: boolean;
  gestion?: 'radicada' | 'negada' | 'reenviada' | null;
  gestion_por?: string | null;
  gestion_at?: string | null;
  gestion_detalle?: string | null;
  carpeta?: string | null;
  solicitud?: { id: number; estado: string; nombre_quien_responde: string | null; created_at: string } | null;
}

interface GmailInfo {
  configurado: boolean;
  buzon: string | null;
  ultima_sync: string | null;
  ultimo_error: string | null;
}

const mensajes = ref<InboxItem[]>([]);
const seleccionado = ref<InboxItem | null>(null);

const esCorreoSolicitud = computed<boolean>(() => {
  const m = seleccionado.value;
  if (!m) {
    return false;
  }
  const texto = `${m.asunto ?? ''} ${m.snippet ?? ''} ${m.cuerpo_texto ?? ''}`;
  return /remisi[oó]n|referenc|solicitud/i.test(texto);
});

const authStore = useAuthStore();
const panelNegar = ref(false);
const fechaNegar = ref('');
const horaNegar = ref('');
const horaNegacion = ref('');
const formNegar = ref<{ eps: string; grupo_negacion: string; motivo_negacion: string; observaciones: string; correos_extra: string[] }>({ eps: '', grupo_negacion: '', motivo_negacion: '', observaciones: '', correos_extra: [] });

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

const panelAceptar = ref(false);
const formSol = ref({
  tipo_documento: '', numero_documento: '', nombres: '', apellidos: '',
  edad: '', genero: '', eps: '', telefono_paciente: '',
  municipio: '', direccion: '', urgencia_vital: false, historia_clinica: '', diagnostico: '',
  especialidad: '', tipo_referencia: '', servicio: '',
  aislamiento: false, tipo_aislamiento: '', gestante: false, condicion_especial: '',
});

const carpetas = ref<{ nombre: string; total: number }[]>([{ nombre: 'Gestionados', total: 0 }, { nombre: 'Negados', total: 0 }]);
const filtroCarpeta = ref('');
const carpetaAceptar = ref('Gestionados');
const carpetaNegar = ref('Negados');
const carpetaReenviar = ref('Gestionados');

const panelReenviar = ref(false);
const reenviando = ref(false);
const correosClinicas = ref<{ nombre: string; email: string }[]>([]);
const formReenviar = ref({ para: '', nota: '', avisar_remitente: true });

function abrirReenviar(): void {
  panelNegar.value = false;
  panelAceptar.value = false;
  carpetaReenviar.value = 'Gestionados';
  formReenviar.value = {
    para: '',
    nota: 'Este correo llegó a nuestra central de referencia por error; lo reenviamos para su gestión.',
    avisar_remitente: true,
  };
  panelReenviar.value = true;
  if (!correosClinicas.value.length) {
    http.get('/api/inbox/correos-clinicas')
      .then(({ data }) => { correosClinicas.value = data.data ?? []; })
      .catch(() => { /* Las sugerencias son una ayuda: sin ellas se escribe el correo a mano. */ });
  }
}

async function confirmarReenvio(): Promise<void> {
  if (!seleccionado.value || reenviando.value) return;
  const para = formReenviar.value.para.trim();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(para)) {
    notify.warning('Escribe el correo del destinatario.');
    return;
  }
  reenviando.value = true;
  try {
    const { data } = await http.post(`/api/inbox/${seleccionado.value.id}/reenviar`, {
      para,
      nota: formReenviar.value.nota.trim() || undefined,
      avisar_remitente: formReenviar.value.avisar_remitente,
      carpeta: carpetaReenviar.value,
    });
    notify.success(data.message || 'Correo reenviado.');
    if (seleccionado.value && data.data) {
      Object.assign(seleccionado.value, {
        gestion: data.data.gestion,
        gestion_por: data.data.gestion_por,
        gestion_at: data.data.gestion_at,
        gestion_detalle: data.data.gestion_detalle,
        carpeta: data.data.carpeta,
      });
      const item = mensajes.value.find((m) => m.id === seleccionado.value!.id);
      if (item) Object.assign(item, { gestion: data.data.gestion, gestion_por: data.data.gestion_por, gestion_detalle: data.data.gestion_detalle, carpeta: data.data.carpeta });
    }
    panelReenviar.value = false;
    void cargar(true);
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo reenviar el correo.');
  } finally {
    reenviando.value = false;
  }
}
const nombresCarpetas = computed(() => carpetas.value.map((c) => c.nombre));

function abrirNegar(): void {
  carpetaNegar.value = 'Negados';
  const recibido = seleccionado.value?.recibido_en ?? '';
  fechaNegar.value = recibido.slice(0, 10);
  horaNegar.value = recibido.slice(11, 16);
  horaNegacion.value = new Date().toTimeString().slice(0, 5);
  formNegar.value = { eps: '', grupo_negacion: '', motivo_negacion: '', observaciones: '', correos_extra: [] };
  panelAceptar.value = false;
  panelNegar.value = true;
}

const prellenados = ref<string[]>([]);

const AUTO_CAMPOS: Record<string, string> = {
  tipo_documento: 'tipo de doc.', numero_documento: 'documento',
  nombres: 'nombres', apellidos: 'apellidos', edad: 'edad', genero: 'género',
  eps: 'EPS', telefono_paciente: 'teléfono', municipio: 'municipio',
  diagnostico: 'diagnóstico', especialidad: 'especialidad',
  historia_clinica: 'historia clínica', tipo_referencia: 'tipo de referencia',
};

/** Texto sin tildes y en mayúsculas — para buscar nombres de EPS/especialidades. */
function norm(s: string): string {
  return s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toUpperCase();
}

// Nombres de pila comunes en Colombia — además de la lista, la terminación
// "a" suele ser femenino y "o" masculino. Es una ayuda, no una certeza: el
// campo queda editable siempre.
const NOMBRES_M = new Set([
  'ALBERTO', 'ALEJANDRO', 'ALFONSO', 'ALFREDO', 'ALVARO', 'ANDRES', 'ANTONIO', 'ARIEL', 'ARMANDO', 'ARTURO',
  'BERNARDO', 'BRAYAN', 'CAMILO', 'CARLOS', 'CESAR', 'CRISTIAN', 'DANIEL', 'DARIO', 'DAVID', 'DIEGO',
  'DUBAN', 'EDGAR', 'EDUARDO', 'EDWIN', 'ELKIN', 'EMILIO', 'ENRIQUE', 'ERNESTO', 'ESTEBAN', 'FABIAN',
  'FABIO', 'FELIPE', 'FELIX', 'FERNANDO', 'FRANCISCO', 'FREDDY', 'GABRIEL', 'GERMAN', 'GIOVANNY', 'GONZALO',
  'GUILLERMO', 'GUSTAVO', 'HECTOR', 'HERNAN', 'HUGO', 'IGNACIO', 'IVAN', 'JAIME', 'JAIRO', 'JAVIER',
  'JEAN', 'JESUS', 'JHON', 'JHONY', 'JORGE', 'JOSE', 'JUAN', 'JULIAN', 'JULIO', 'LAZARO', 'LEANDRO',
  'LEONARDO', 'LUIS', 'MANUEL', 'MARCO', 'MARCOS', 'MARIO', 'MARLON', 'MARTIN', 'MATEO', 'MAURICIO',
  'MIGUEL', 'NELSON', 'NESTOR', 'NICOLAS', 'OCTAVIO', 'OMAR', 'ORLANDO', 'OSCAR', 'OSWALDO', 'PABLO',
  'PEDRO', 'RAFAEL', 'RAMIRO', 'RAMON', 'RAUL', 'RICARDO', 'RIGOBERTO', 'ROBERTO', 'RODRIGO', 'RUBEN',
  'SALVADOR', 'SAMUEL', 'SANTIAGO', 'SEBASTIAN', 'SERGIO', 'SIMON', 'TOMAS', 'UBALDO', 'VICTOR', 'VLADIMIR',
  'WALTER', 'WILLIAM', 'WILMAR', 'WILSON', 'YEISON', 'YESID',
]);

const NOMBRES_F = new Set([
  'ADRIANA', 'ALBA', 'ALEJANDRA', 'AMPARO', 'ANA', 'ANDREA', 'ANGELA', 'AURA', 'AURORA', 'BEATRIZ',
  'BERTHA', 'BLANCA', 'CAMILA', 'CARMEN', 'CAROLINA', 'CATALINA', 'CECILIA', 'CLAUDIA', 'CONSUELO', 'DANIELA',
  'DIANA', 'DORA', 'ELBA', 'ELENA', 'ELIANA', 'ELSA', 'ELVIA', 'ESPERANZA', 'ESTELA', 'FABIOLA',
  'FANY', 'FATIMA', 'FLOR', 'GABRIELA', 'GINA', 'GLORIA', 'HILDA', 'INES', 'IRMA', 'ISABEL', 'ISABELLA',
  'JACQUELINE', 'JENNY', 'JOHANA', 'JOSEFINA', 'JUANA', 'JUDY', 'JULIANA', 'KARINA', 'KATHERINE', 'KELLY',
  'LADY', 'LAURA', 'LEIDY', 'LEONOR', 'LILIANA', 'LISBETH', 'LORENA', 'LUZ', 'MAGALY', 'MANUELA',
  'MARCELA', 'MARGARITA', 'MARIA', 'MARTHA', 'MERCEDES', 'MILENA', 'MONICA', 'NANCY', 'NATALIA', 'NELLY',
  'NIDIA', 'NORMA', 'NUBIA', 'OFELIA', 'OLGA', 'OMAIRA', 'PAOLA', 'PATRICIA', 'PIEDAD', 'PRISCILA',
  'RAQUEL', 'ROCIO', 'ROSA', 'RUTH', 'SANDRA', 'SARA', 'SILVIA', 'SINDY', 'SOFIA', 'SULMA',
  'TATIANA', 'TERESA', 'VALENTINA', 'VILMA', 'VIVIANA', 'XIMENA', 'YADIRA', 'YAMILE', 'YEIMY', 'YENNY',
  'YESSICA', 'YOLANDA', 'YULY', 'YURY', 'ZORAIDA', 'ZULAY', 'ZULMA',
]);

/** Adivina el género por el primer nombre — vacío cuando no se reconoce. */
function generoPorNombre(nombre: string): 'M' | 'F' | '' {
  const primero = norm(nombre).split(/\s+/)[0] ?? '';
  if (NOMBRES_F.has(primero)) return 'F';
  if (NOMBRES_M.has(primero)) return 'M';
  if (primero.endsWith('A')) return 'F';
  if (primero.endsWith('O')) return 'M';
  return '';
}

// Conectores que abren apellido compuesto: "De La Cruz", "Del Valle", "Van der Berg".
const CONECTORES_APELLIDO = new Set(['DE', 'DEL', 'LA', 'LOS', 'LAS', 'DA', 'DI', 'VAN', 'VON', 'DER']);

// Frases que son parte de nombres compuestos frecuentes, no de apellidos:
// "María del Carmen", "Juan de Dios", "María de los Ángeles". Solo cuentan
// cuando además queda texto después (si no, el conector sí abre el apellido).
const NOMBRES_COMPUESTOS = ['DEL CARMEN', 'DE DIOS', 'DE JESUS', 'DE LOS ANGELES', 'DE LAS MERCEDES', 'DE LA CRUZ'];

// Conectores en minúscula dentro de un nombre (María de la Cruz, Van der Berg).
const CONECTORES_MINUS = new Set(['de', 'del', 'la', 'los', 'las', 'y', 'da', 'di', 'van', 'von', 'der']);

/**
 * Recorta de una captura solo lo que parece nombre: palabras que empiezan
 * en mayúscula o conectores. En cuanto aparece texto normal ("que requiere…")
 * o un signo, se corta — así "Paciente: KELLY CORREA que requiere remisión"
 * deja solo "KELLY CORREA".
 */
function cortarNombre(crudo: string): string {
  const partes = crudo.split(/[,;:|(]/)[0].trim().split(/\s+/).filter(Boolean);
  const utiles: string[] = [];
  for (const w of partes) {
    if (/^[A-ZÁÉÍÓÚÑ][\p{L}'-]*$/u.test(w) || (utiles.length > 0 && CONECTORES_MINUS.has(w.toLowerCase()))) {
      utiles.push(w);
    } else {
      break;
    }
  }

  return utiles.slice(0, 8).join(' ');
}

/**
 * Reparte un nombre completo en nombres + apellidos con reglas del formato
 * usual colombiano: 2 palabras = 1+1, 3 = 1+2, 4 = 2+2, más = 2+resto; un
 * conector abre el apellido salvo que forme nombre compuesto.
 */
function repartirNombre(completo: string): { nombres: string; apellidos: string } {
  const p = completo.trim().split(/\s+/).filter(Boolean);
  if (p.length <= 1) return { nombres: completo.trim(), apellidos: '' };
  const up = p.map(norm);

  for (let i = 1; i <= p.length - 2; i++) {
    if (!CONECTORES_APELLIDO.has(up[i])) continue;
    const resto = up.slice(i).join(' ');
    const esNombreCompuesto = NOMBRES_COMPUESTOS.some((c) => resto.startsWith(c) && up.slice(i).length > c.split(' ').length);
    if (!esNombreCompuesto) {
      return { nombres: p.slice(0, i).join(' '), apellidos: p.slice(i).join(' ') };
    }
  }

  const nLen = p.length <= 3 ? 1 : 2;
  return { nombres: p.slice(0, nLen).join(' '), apellidos: p.slice(nLen).join(' ') };
}

/** Lee el correo y pre-llena lo que reconozca con confianza — el resto se digita a mano. */
function extraerDatosDelCorreo(): void {
  const m = seleccionado.value;
  prellenados.value = [];
  if (!m) return;

  // Solo asunto + cuerpo: el "snippet" de Gmail viene aplastado en una sola
  // línea y confunde los cortes por etiqueta.
  const texto = `${m.asunto ?? ''}\n${m.cuerpo_texto ?? ''}`;
  const liso = norm(texto);
  const llenados: string[] = [];
  const poner = (campo: string, valor: string): void => {
    if (valor && !(formSol.value as Record<string, string>)[campo]) {
      (formSol.value as Record<string, string>)[campo] = valor;
      llenados.push(campo);
    }
  };

  // Documento con tipo explícito ("CC 123456789", "T.I. 123456") o con etiqueta ("documento: 123456").
  const conTipo = texto.match(/\b(AS|CC|CE|CN|MS|PA|PE|PT|RC|SC|TI)\.?\s*[:#\-]?\s*(\d[\d.\s]{4,13}\d)\b/i);
  if (conTipo) {
    poner('tipo_documento', conTipo[1].toUpperCase());
    poner('numero_documento', conTipo[2].replace(/[\s.]/g, ''));
  } else {
    const conEtiqueta = texto.match(/(?:documento|identificaci[oó]n|c[eé]dula)[^\d]{0,20}(\d[\d.\s]{4,13}\d)/i);
    if (conEtiqueta) poner('numero_documento', conEtiqueta[1].replace(/[\s.]/g, ''));
  }

  // Etiquetas que abren un dato en estos correos. Sirven también de borde:
  // cada valor termina donde empieza la etiqueta siguiente, así el correo
  // venga en líneas separadas o con varios campos pegados en una sola línea.
  const ETIQUETA = String.raw`nombres?(?:\s+y\s+apellidos?)?|apellidos?|nombre\s+(?:completo|del?\s*paciente)|paciente\s+se\s+encuentra\s+en|tipo\s+y\s+n[úu]mero\s+de\s+documento|n[úu]mero\s+de\s+documento|documento|identificaci[óo]n|c[ée]dula|edad|g[ée]nero|sexo|fecha\s+de\s+nacimiento|diagn[óo]stico|servicio(?:\s+o\s+especialidad)?\s+requeridos?|especialidad|servicio|municipio|ciudad|localidad|eps|aseguradora|entidad\s+de\s+salud|tel[ée]fono|celular|direcci[óo]n|historia\s*cl[íi]nica|motivo(?:\s+de\s+(?:remisi[óo]n|consulta|referencia))?|observaciones|antecedentes|resumen\s*cl[íi]nico|remite|ips|r[ée]gimen|acompañante|garantor|adjunto`;

  /** Valor tras una etiqueta, cortado donde empieza la siguiente o se acaba el texto. */
  const valor = (etiqueta: string, max = 250): string => {
    const re = new RegExp(`(?:^|[\\s.;,)>])\\s*(?:${etiqueta})\\s*[:.]\\s*([\\s\\S]{2,${max}}?)(?=\\s*(?:${ETIQUETA})\\s*[:.]|\\s*$)`, 'i');
    const cap = texto.match(re)?.[1] ?? '';

    return cap.replace(/\s+/g, ' ').trim();
  };

  // Nombre: las etiquetas propias "Nombre(s):" / "Apellidos:" son la fuente
  // fuerte; si el campo trae el nombre completo se reparte. De respaldo va
  // "Paciente:" solo al inicio de línea u oración — "del paciente:" a mitad
  // de frase no cuenta — y debe rendir al menos 2 palabras de nombre.
  const soloNombres = cortarNombre(valor('nombres?(?:\\s+y\\s+apellidos?)?|nombre\\s+(?:completo|del?\\s*paciente)', 80));
  const soloApellidos = cortarNombre(valor('apellidos?', 50));
  const pc = texto.match(new RegExp(`(?:^|\\n|[.;!?]\\s+)\\s*paciente\\s*[:.]\\s*([\\s\\S]{2,80}?)(?=\\s*(?:${ETIQUETA})\\s*[:.]|\\s*$)`, 'i'))?.[1] ?? '';
  const nombreCompleto = cortarNombre(pc);

  if (soloApellidos) {
    poner('nombres', soloNombres);
    poner('apellidos', soloApellidos);
  } else {
    const fuente = soloNombres || (nombreCompleto.split(/\s+/).length >= 2 ? nombreCompleto : '');
    if (fuente) {
      const r = repartirNombre(fuente);
      poner('nombres', r.nombres);
      poner('apellidos', r.apellidos);
    }
  }

  // Género: primero la etiqueta del correo; si no, la sugerencia por nombre.
  const genCorreo = valor('g[ée]nero|sexo', 15).match(/fem|mascul|mujer|hombre|^\s*f\b|^\s*m\b/i)?.[0];
  if (genCorreo) {
    poner('genero', /fem|mujer|^\s*f/i.test(genCorreo) ? 'Femenino' : 'Masculino');
  }
  const gen = generoPorNombre(formSol.value.nombres);
  if (gen) poner('genero', gen);

  const edad = texto.match(/edad\s*[:.]?\s*(\d{1,3})/i) ?? texto.match(/(\d{1,3})\s*a[nñ]os/i);
  if (edad) poner('edad', edad[1]);

  const tel = texto.match(/tel[eé]fono[^\d]{0,15}(\+?[\d\s\-()]{7,15})/i) ?? texto.match(/\b(3\d{9})\b/);
  if (tel) poner('telefono_paciente', tel[1].replace(/[\s\-()]/g, ''));

  const mun = valor('municipio|ciudad|localidad', 40);
  if (mun) poner('municipio', mun);

  const dx = valor('diagn[óo]stico(?:\s+principal)?|dx', 300);
  if (dx) poner('diagnostico', dx);

  const hc = valor('motivo\\s+de\\s+(?:remisi[óo]n|consulta|referencia)|observaciones|antecedentes|resumen\\s*cl[íi]nico', 400);
  if (hc) poner('historia_clinica', hc);

  // "Paciente se encuentra en: CLINICA X (MUNICIPIO-DEPTO)" — el paréntesis
  // da el municipio y la línea completa sirve de contexto clínico.
  const ubi = valor('paciente\\s+se\\s+encuentra\\s+en', 140);
  if (ubi) {
    poner('historia_clinica', `Paciente se encuentra en: ${ubi}`);
    const munUbi = ubi.match(/\(\s*([A-ZÁÉÍÓÚÑ][^()\d]{1,40}?)\s*[-–]/);
    if (munUbi) poner('municipio', munUbi[1].trim());
  }

  // EPS y especialidad: cualquier nombre de las listas del sistema que aparezca en el correo.
  const enLista = (lista: readonly string[]): string | undefined =>
    [...lista].sort((a, b) => b.length - a.length).find((item) => liso.includes(norm(item)));
  const eps = enLista(EPS_LIST);
  if (eps) poner('eps', eps);
  const esp = enLista(ESPECIALIDADES);
  if (esp) poner('especialidad', esp);

  // Tipo de referencia por el nombre usado en los correos ("remisión integral").
  if (/remisi[oó]n\s+integral/i.test(liso)) poner('tipo_referencia', 'Referencia integral');
  else if (/remisi[oó]n\s+redonda/i.test(liso)) poner('tipo_referencia', 'Referencia redonda');

  prellenados.value = llenados.map((c) => AUTO_CAMPOS[c] ?? c);
}

function abrirAceptar(): void {
  duplicadas.value = [];
  carpetaAceptar.value = 'Gestionados';
  const recibido = seleccionado.value?.recibido_en ?? '';
  fechaNegar.value = recibido.slice(0, 10);
  horaNegar.value = recibido.slice(11, 16);
  horaNegacion.value = new Date().toTimeString().slice(0, 5);
  formSol.value = {
    tipo_documento: 'CC', numero_documento: '', nombres: '', apellidos: '',
    edad: '', genero: '', eps: '', telefono_paciente: '',
    municipio: '', direccion: '', urgencia_vital: false, historia_clinica: '', diagnostico: '',
    especialidad: '', tipo_referencia: '', servicio: '',
    aislamiento: false, tipo_aislamiento: '', gestante: false, condicion_especial: '',
  };
  panelNegar.value = false;
  panelAceptar.value = true;
  extraerDatosDelCorreo();
}

const duplicadas = ref<Array<{ id: number; paciente: string; clinica: string | null; estado: string; creada: string | null }>>([]);
let dupTimer: ReturnType<typeof setTimeout> | undefined;

watch(
  () => [formSol.value.tipo_documento, formSol.value.numero_documento],
  () => {
    clearTimeout(dupTimer);
    duplicadas.value = [];
    const doc = formSol.value.numero_documento.trim();
    if (doc.length < 4) return;
    dupTimer = setTimeout(async () => {
      try {
        const { data } = await http.get('/api/inbox/verificar-documento', {
          params: { numero_documento: doc, tipo_documento: formSol.value.tipo_documento || undefined },
        });
        duplicadas.value = data.duplicadas ?? [];
      } catch {
        // El aviso es una ayuda: si falla la consulta no se bloquea la radicación.
      }
    }, 400);
  },
);

// Al digitar el nombre a mano, el género se sugiere solo — sigue editable.
watch(
  () => formSol.value.nombres,
  (v) => {
    if (!formSol.value.genero) {
      const g = generoPorNombre(v);
      if (g) formSol.value.genero = g;
    }
  },
);

const dupDetalle = ref<any>(null);
const dupCargando = ref(false);

const ESTADO_LABELS: Record<string, string> = {
  pendiente: 'Nueva solicitud',
  pendiente_cama: 'En espera de cupo',
  en_espera: 'Aceptado',
  completado: 'Completada',
  negado: 'Negada',
  cancelado: 'Cancelada',
};

function estadoLabel(e: string): string {
  return ESTADO_LABELS[e] ?? e;
}

function nombreCompleto(s: any): string {
  return [s.primer_nombre, s.segundo_nombre, s.primer_apellido, s.segundo_apellido].filter(Boolean).join(' ') || '—';
}

async function verDuplicada(id: number): Promise<void> {
  dupDetalle.value = null;
  dupCargando.value = true;
  try {
    const { data } = await http.get(`/api/solicitudes-referencia/${id}`);
    dupDetalle.value = data.data;
  } catch {
    notify.error('No se pudo cargar la solicitud.');
  } finally {
    dupCargando.value = false;
  }
}

const registrando = ref(false);

async function confirmarAceptacion(): Promise<void> {
  const f = formSol.value;
  if (!f.tipo_documento || !f.numero_documento.trim() || !f.nombres.trim() || !f.apellidos.trim()
    || !f.edad.trim() || !f.genero || !f.eps
    || !f.historia_clinica.trim() || !f.diagnostico.trim()
    || !f.especialidad || !f.tipo_referencia || !f.servicio) {
    notify.warning('Complete los campos obligatorios');
    return;
  }
  try {
    registrando.value = true;
    const { data } = await http.post(`/api/inbox/${seleccionado.value!.id}/solicitud`, { ...f, carpeta: carpetaAceptar.value });
    notify.success(data.message || 'Solicitud registrada.');
    if (seleccionado.value && data.data) {
      seleccionado.value.solicitud = {
        id: data.data.id,
        estado: data.data.estado,
        nombre_quien_responde: data.data.nombre_quien_responde,
        created_at: data.data.created_at,
      };
      seleccionado.value.gestion = 'radicada';
      seleccionado.value.gestion_por = data.data.nombre_quien_responde;
      seleccionado.value.gestion_detalle = `Solicitud #${data.data.id} registrada.`;
      seleccionado.value.carpeta = carpetaAceptar.value;
      const item = mensajes.value.find((m) => m.id === seleccionado.value!.id);
      if (item) {
        item.solicitud = seleccionado.value.solicitud;
        item.gestion = 'radicada';
        item.gestion_por = seleccionado.value.gestion_por;
        item.carpeta = carpetaAceptar.value;
      }
    }
    panelAceptar.value = false;
    void cargar(true);
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo registrar la solicitud.');
  } finally {
    registrando.value = false;
  }
}

const negando = ref(false);

async function confirmarNegacion(): Promise<void> {
  const f = formNegar.value;
  if (!f.eps || !horaNegacion.value || !f.grupo_negacion || !f.motivo_negacion.trim()) {
    notify.warning('Complete los campos obligatorios');
    return;
  }
  if (EPS_CON_CONTRATO.includes(f.eps.trim().toUpperCase())) {
    notify.warning('Seleccione la EPS con su tipo de contrato (por ejemplo NUEVA EPS PGP o NUEVA EPS EVENTO).');
    return;
  }
  try {
    negando.value = true;
    const { data } = await http.post(`/api/inbox/${seleccionado.value!.id}/negar`, {
      eps: f.eps,
      grupo_negacion: f.grupo_negacion,
      motivo_negacion: f.motivo_negacion.trim(),
      observaciones: f.observaciones?.trim() || undefined,
      correos_extra: f.correos_extra,
      carpeta: carpetaNegar.value,
    });
    notify.success(data.message || 'El correo quedó marcado como negado.');
    if (seleccionado.value && data.data) {
      seleccionado.value.gestion = data.data.gestion;
      seleccionado.value.gestion_por = data.data.gestion_por;
      seleccionado.value.gestion_at = data.data.gestion_at;
      seleccionado.value.gestion_detalle = data.data.gestion_detalle;
      seleccionado.value.carpeta = data.data.carpeta;
      const item = mensajes.value.find((m) => m.id === seleccionado.value!.id);
      if (item) {
        item.gestion = data.data.gestion;
        item.gestion_por = data.data.gestion_por;
        item.gestion_detalle = data.data.gestion_detalle;
        item.carpeta = data.data.carpeta;
      }
    }
    panelNegar.value = false;
    void cargar(true);
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo negar el correo.');
  } finally {
    negando.value = false;
  }
}
const filtro = ref<'todos' | 'no_leidos'>('todos');
const buscar = ref('');
const cargando = ref(false);
const cargandoDetalle = ref(false);
const sincronizando = ref(false);
const noLeidos = ref(0);
const meta = ref({ total: 0, pagina: 1, paginas: 1 });
const gmail = ref<GmailInfo>({ configurado: false, buzon: null, ultima_sync: null, ultimo_error: null });
const gmailCargado = ref(false);

async function cargar(reiniciar = false): Promise<void> {
  cargando.value = true;
  try {
    const pagina = reiniciar ? 1 : meta.value.pagina;
    const { data } = await http.get('/api/inbox', {
      params: {
        page: pagina,
        q: buscar.value || undefined,
        filtro: filtro.value === 'no_leidos' ? 'no_leidos' : undefined,
        carpeta: filtroCarpeta.value || undefined,
      },
    });
    mensajes.value = reiniciar ? data.data : [...mensajes.value, ...data.data];
    meta.value = { total: data.meta.total, pagina: data.meta.pagina, paginas: data.meta.paginas };
    noLeidos.value = data.meta.no_leidos;
    carpetas.value = data.meta.carpetas ?? carpetas.value;
    gmail.value = data.gmail;
    gmailCargado.value = true;
  } catch {
    notify.error('No se pudo cargar la bandeja.');
  } finally {
    cargando.value = false;
  }
}

function cambiarFiltro(f: 'todos' | 'no_leidos'): void {
  if (filtro.value === f) return;
  filtro.value = f;
  seleccionado.value = null;
  void cargar(true);
}

const moviendo = ref(false);

async function moverCarpeta(nombre: string): Promise<void> {
  const m = seleccionado.value;
  if (!m || !nombre?.trim()) return;
  try {
    moviendo.value = true;
    const { data } = await http.post(`/api/inbox/${m.id}/carpeta`, { carpeta: nombre.trim() });
    m.carpeta = data.data.carpeta;
    const item = mensajes.value.find((x) => x.id === m.id);
    if (item) item.carpeta = data.data.carpeta;
    carpetas.value = data.carpetas ?? carpetas.value;
    notify.success(data.message);
    if (filtroCarpeta.value && filtroCarpeta.value !== m.carpeta) void cargar(true);
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo mover el correo.');
  } finally {
    moviendo.value = false;
  }
}

async function enviarPapelera(): Promise<void> {
  const m = seleccionado.value;
  if (!m) return;
  try {
    await ElMessageBox.confirm(
      'Este correo se eliminará definitivamente del aplicativo (no se borra de Gmail).',
      'Eliminar correo',
      { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
    );
  } catch {
    return;
  }
  try {
    moviendo.value = true;
    const { data } = await http.post(`/api/inbox/${m.id}/papelera`);
    mensajes.value = mensajes.value.filter((x) => x.id !== m.id);
    seleccionado.value = null;
    carpetas.value = data.carpetas ?? carpetas.value;
    notify.success(data.message);
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo eliminar el correo.');
  } finally {
    moviendo.value = false;
  }
}

function cambiarCarpeta(): void {
  seleccionado.value = null;
  void cargar(true);
}

function cargarMas(): void {
  meta.value.pagina += 1;
  void cargar();
}

async function abrir(m: InboxItem): Promise<void> {
  seleccionado.value = { ...m, cuerpo_texto: null, cuerpo_html: null };
  panelNegar.value = false;
  panelAceptar.value = false;
  cargandoDetalle.value = true;
  try {
    const { data } = await http.get(`/api/inbox/${m.id}`);
    seleccionado.value = data.data;
    if (!m.leido) {
      m.leido = true;
      noLeidos.value = Math.max(0, noLeidos.value - 1);
    }
  } catch {
    notify.error('No se pudo abrir el correo.');
  } finally {
    cargandoDetalle.value = false;
  }
}

async function sincronizar(): Promise<void> {
  sincronizando.value = true;
  try {
    const { data } = await http.post('/api/inbox/sincronizar', { auditar: true });
    noLeidos.value = data.no_leidos;
    notify.success(data.data.agregados > 0 ? `${data.data.agregados} correo(s) nuevo(s).` : 'Bandeja al día.');
    await cargar(true);
  } catch (e: any) {
    notify.error(e?.response?.data?.message ?? 'No se pudo sincronizar con Gmail.');
  } finally {
    sincronizando.value = false;
  }
}

function nombreRemitente(remitente: string | null): string {
  if (!remitente) return 'Desconocido';
  const m = remitente.match(/^\s*"?([^"<]+)"?\s*</);
  return m ? m[1].trim() : remitente;
}

function fechaCorta(iso: string): string {
  const d = new Date(iso);
  const hoy = new Date();
  const mismoDia = d.toDateString() === hoy.toDateString();
  return mismoDia
    ? d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

function fechaLarga(iso: string): string {
  return new Date(iso).toLocaleString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function tamano(bytes: number): string {
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}

// Refresco silencioso: mientras la bandeja está abierta se dispara el sync
// con Gmail ~cada 60s (el endpoint lleva candado contra duplicados) y la
// lista se recarga cada 15s, sin spinner ni avisos.
const ultimaSyncForzada = ref(0);

async function refrescar(): Promise<void> {
  if (document.hidden) return;
  if (Date.now() - ultimaSyncForzada.value > 60000) {
    ultimaSyncForzada.value = Date.now();
    // Sin await: la lista se recarga en paralelo, no espera a Gmail.
    void http.post('/api/inbox/sincronizar').catch(() => { /* el próximo ciclo reintenta */ });
  }
  try {
    const { data } = await http.get('/api/inbox', {
      params: {
        page: 1,
        q: buscar.value || undefined,
        filtro: filtro.value === 'no_leidos' ? 'no_leidos' : undefined,
        carpeta: filtroCarpeta.value || undefined,
      },
    });
    noLeidos.value = data.meta.no_leidos;
    carpetas.value = data.meta.carpetas ?? carpetas.value;
    gmail.value = data.gmail;
    gmailCargado.value = true;
    meta.value.total = data.meta.total;
    meta.value.paginas = data.meta.paginas;

    const nuevos = data.data as InboxItem[];
    if (meta.value.pagina <= 1) {
      mensajes.value = nuevos;
      return;
    }

    const cargados = new Set(mensajes.value.map(m => m.id));
    const recientes = nuevos.filter(m => !cargados.has(m.id));
    const mapa = new Map(nuevos.map(m => [m.id, m]));
    mensajes.value = [
      ...recientes,
      ...mensajes.value.map(m => mapa.get(m.id) ?? m),
    ];
  } catch { /* refresco silencioso: el próximo ciclo reintenta */ }
}

let temporizador: number | undefined;
onMounted(() => {
  void cargar(true);
  temporizador = window.setInterval(() => void refrescar(), 15000);
});
onUnmounted(() => window.clearInterval(temporizador));
</script>

<style scoped>
.inbx-page { background: linear-gradient(165deg, #eaf1fb 0%, #f4f7fd 45%, #eef2fa 100%); }
.dark .inbx-page { background: transparent; }
.inbx-wrap { display: flex; height: 100%; min-height: 0; flex-direction: column; }

.inbx-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 8px; }
.inbx-head-icon { display: grid; width: 30px; height: 30px; flex-shrink: 0; place-items: center; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1e40af); color: #fff; box-shadow: 0 4px 12px rgba(37,99,235,.3); }
.inbx-head h1 { margin: 0; color: #0d2d5e; font-size: 16px; font-weight: 800; }
.dark .inbx-head h1 { color: #dbe7fb; }
.inbx-head small { color: #64748b; font-size: 11px; font-weight: 600; }
.inbx-badge { padding: 3px 10px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 11px; font-weight: 800; }
.inbx-head-actions { display: flex; flex: 1; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }
.inbx-tabs { display: inline-flex; overflow: hidden; border: 1px solid #c3d6f2; border-radius: 10px; background: #fff; }
.inbx-tabs button { border: none; padding: 6px 12px; background: transparent; color: #475569; font-size: 11.5px; font-weight: 700; cursor: pointer; }
.inbx-tabs button.inbx-tab-on { background: #2563eb; color: #fff; }
.dark .inbx-tabs { border-color: #2c3f5e; background: #161b28; }
.dark .inbx-tabs button { color: #cbd5e1; }
.dark .inbx-tabs button.inbx-tab-on { background: #4f46e5; }
.inbx-search { width: 220px; }
.inbx-carpeta-filtro { width: 190px; }
.inbx-mover { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; padding: 6px 10px; font-size: 11.5px; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }
.inbx-mover-sel { width: 220px; }
.dark .inbx-mover { color: #cbd5e1; background: #161b28; border-color: #2c3f5e; }
.inbx-trash { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; font-size: 11.5px; font-weight: 700; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 999px; cursor: pointer; transition: all 0.15s; }
.inbx-trash:hover:not(:disabled) { background: #fee2e2; border-color: #fca5a5; transform: translateY(-1px); }
.inbx-dup { display: flex; gap: 10px; padding: 10px 12px; font-size: 12px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; }
.inbx-dup-ico { flex-shrink: 0; margin-top: 1px; color: #d97706; }
.inbx-dup-info { display: flex; flex-direction: column; gap: 3px; }
.inbx-dup-info a { color: #b45309; font-weight: 700; text-decoration: underline; }
.dark .inbx-dup { color: #fbbf24; background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.3); }
.dark .inbx-dup-ico { color: #fbbf24; }
.dark .inbx-dup-info a { color: #fcd34d; }
.inbx-trash:disabled { opacity: 0.5; cursor: not-allowed; }
.dark .inbx-trash { color: #f87171; background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.3); }
.dark .inbx-trash:hover:not(:disabled) { background: rgba(239, 68, 68, 0.16); }
.inbx-carpeta-sel { margin: 0 14px 8px; }
.inbx-negar-label svg { display: inline-block; vertical-align: -2px; margin-right: 2px; }
.inbx-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; padding: 5px 14px; border: none; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); }
.inbx-btn:disabled { opacity: .5; cursor: not-allowed; }

.inbx-alerta { margin: 0 0 8px; padding: 9px 12px; border: 1px solid #fcd9d9; border-radius: 10px; background: #fef2f2; color: #b91c1c; font-size: 12px; }
.dark .inbx-alerta { border-color: #5b2330; background: #2b1620; color: #fda4af; }
.inbx-sync-info { margin: 0 0 8px; color: #94a3b8; font-size: 11px; font-weight: 600; }

.inbx-body { display: grid; flex: 1; grid-template-columns: minmax(300px, 380px) minmax(0, 1fr); gap: 8px; min-height: 0; }
.inbx-list { display: flex; flex-direction: column; gap: 6px; overflow-y: auto; padding-right: 2px; scrollbar-width: thin; scrollbar-color: rgba(96,140,200,.5) transparent; }
.inbx-list::-webkit-scrollbar { width: 5px; }
.inbx-list::-webkit-scrollbar-thumb { background: rgba(96,140,200,.5); border-radius: 99px; }

.inbx-item { position: relative; display: flex; flex-direction: column; gap: 3px; padding: 9px 12px; border: 1px solid #dbe6f5; border-radius: 12px; background: #fff; text-align: left; cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease; }
.inbx-item:hover { border-color: #93b8ee; box-shadow: 0 4px 12px rgba(22,70,142,.1); }
.inbx-item-on { border-color: #2563eb; box-shadow: 0 4px 14px rgba(37,99,235,.16); }
.inbx-item-new { background: #f0f6ff; }
.inbx-item-rad {
  border-color: #34d399; border-left: 4px solid #10b981; padding-left: 8px;
  background: linear-gradient(135deg, #d3f8e4 0%, #e9fbf1 60%, #f4fdf8 100%);
  box-shadow: 0 3px 10px rgba(16,185,129,.14);
}
.inbx-item-rad.inbx-item-on { border-color: #10b981; border-left-color: #059669; box-shadow: 0 4px 16px rgba(16,185,129,.3); }
.inbx-item-neg {
  border-color: #f87171; border-left: 4px solid #ef4444; padding-left: 8px;
  background: linear-gradient(135deg, #fddcdc 0%, #feecec 60%, #fff7f7 100%);
  box-shadow: 0 3px 10px rgba(239,68,68,.14);
}
.inbx-item-neg.inbx-item-on { border-color: #ef4444; border-left-color: #dc2626; box-shadow: 0 4px 16px rgba(239,68,68,.3); }
.dark .inbx-item-rad { border-color: rgba(52,211,153,.45); border-left-color: #10b981; background: linear-gradient(135deg, #153527 0%, #18261f 60%, #161b28 100%); }
.dark .inbx-item-rad.inbx-item-on { border-color: #34d399; border-left-color: #6ee7b7; }
.dark .inbx-item-neg { border-color: rgba(248,113,113,.45); border-left-color: #ef4444; background: linear-gradient(135deg, #3a1e22 0%, #2a1a1e 60%, #161b28 100%); }
.dark .inbx-item-neg.inbx-item-on { border-color: #f87171; border-left-color: #fca5a5; }
.dark .inbx-item { border-color: #263352; background: #161b28; }
.dark .inbx-item-new { background: #182238; }
.dark .inbx-item-on { border-color: #4f46e5; }
.inbx-item-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.inbx-from { overflow: hidden; color: #0d2d5e; font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.inbx-item-new .inbx-from { font-weight: 800; }
.dark .inbx-from { color: #dbe7fb; }
.inbx-item-top time { flex-shrink: 0; color: #94a3b8; font-size: 10.5px; font-weight: 600; }
.inbx-subject { overflow: hidden; color: #334155; font-size: 11.5px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.inbx-item-new .inbx-subject { font-weight: 800; color: #1e3a5f; }
.dark .inbx-subject { color: #cbd5e1; }
.inbx-snippet { display: -webkit-box; overflow: hidden; color: #94a3b8; font-size: 10.5px; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.inbx-item-meta { display: flex; align-items: center; justify-content: flex-end; gap: 6px; color: #94a3b8; }
.inbx-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #2563eb; }
.inbx-mas { margin-top: 2px; padding: 8px; border: 1px dashed #c3d6f2; border-radius: 10px; background: transparent; color: #2563eb; font-size: 11.5px; font-weight: 700; cursor: pointer; }
.dark .inbx-mas { border-color: #2c3f5e; color: #93c5fd; }

.inbx-reader { display: flex; min-width: 0; flex-direction: column; overflow: hidden; border: 1px solid #86a5d8; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(22,70,142,.14); }
.dark .inbx-reader { border-color: rgba(255,255,255,.07); background: #161b28; box-shadow: none; }
.inbx-reader-head { padding: 14px 16px 10px; border-bottom: 1px solid #edf2f9; }
.dark .inbx-reader-head { border-color: #22304a; }
.inbx-reader-head h2 { margin: 0 0 6px; color: #0d2d5e; font-size: 15px; font-weight: 800; }
.dark .inbx-reader-head h2 { color: #dbe7fb; }
.inbx-reader-head p { margin: 0 0 2px; color: #475569; font-size: 12px; }
.dark .inbx-reader-head p { color: #cbd5e1; }
.inbx-reader-head time { color: #94a3b8; font-size: 11px; font-weight: 600; }
.inbx-adjuntos { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.inbx-adjunto { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border: 1px solid #c3d6f2; border-radius: 999px; background: #f4f8ff; color: #315583; font-size: 11px; font-weight: 600; }
.dark .inbx-adjunto { border-color: #2c3f5e; background: #1a2233; color: #9db8e0; }
.inbx-adjunto em { color: #94a3b8; font-style: normal; }
.inbx-texto { flex: 1; overflow: auto; margin: 0; padding: 16px; color: #1e293b; font-family: inherit; font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
.dark .inbx-texto { color: #dbe7fb; }
.inbx-html { flex: 1; width: 100%; border: none; background: #fff; }
.inbx-vacio { margin: auto; padding: 40px 16px; color: #94a3b8; font-size: 12.5px; text-align: center; }

.inbx-sol {
  display: flex; align-items: center; justify-content: space-between; gap: 14px;
  padding: 10px 16px;
  border-bottom: 1px solid #dbe8fb;
  background: linear-gradient(135deg, #eff6ff 0%, #f5f9ff 55%, #eef4ff 100%);
}
.dark .inbx-sol { border-color: #22304a; background: linear-gradient(135deg, #16222f 0%, #1a2233 100%); }
.inbx-sol-info { display: flex; align-items: center; gap: 10px; min-width: 0; }
.inbx-sol-ico {
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  width: 32px; height: 32px; border-radius: 10px; color: #fff;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  box-shadow: 0 3px 8px rgba(37,99,235,.3), inset 0 1px 0 rgba(255,255,255,.25);
}
.inbx-sol-ico svg { width: 16px; height: 16px; }
.inbx-sol-txt { display: flex; flex-direction: column; min-width: 0; }
.inbx-sol-txt strong { font-size: 12.5px; font-weight: 700; letter-spacing: .01em; color: #1e3a8a; }
.inbx-sol-txt span { font-size: 10.5px; color: #5b7bb4; }
.dark .inbx-sol-txt strong { color: #bfdbfe; }
.dark .inbx-sol-txt span { color: #8fa8cf; }
.inbx-sol-btns { display: flex; gap: 8px; flex-shrink: 0; }
.inbx-sol-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 5px 15px 5px 5px; border: none; border-radius: 999px;
  color: #fff; font-size: 12px; font-weight: 700; letter-spacing: .02em;
  cursor: pointer;
  transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
}
.inbx-sol-btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
.inbx-sol-btn:active { transform: translateY(0); }
.inbx-sol-btnico {
  display: inline-flex; align-items: center; justify-content: center;
  width: 24px; height: 24px; border-radius: 50%;
  background: rgba(255,255,255,.22);
}
.inbx-sol-btnico svg { width: 13px; height: 13px; }
.inbx-sol-ok { background: linear-gradient(135deg, #34d399, #059669); box-shadow: 0 4px 12px rgba(5,150,105,.3); }
.inbx-sol-ok:hover { box-shadow: 0 6px 16px rgba(5,150,105,.4); }
.inbx-sol-no { background: linear-gradient(135deg, #f87171, #dc2626); box-shadow: 0 4px 12px rgba(220,38,38,.3); }
.inbx-sol-no:hover { box-shadow: 0 6px 16px rgba(220,38,38,.4); }
.inbx-sol-del { background: linear-gradient(135deg, #94a3b8, #64748b); box-shadow: 0 4px 12px rgba(100,116,139,.3); }
.inbx-sol-del:hover { box-shadow: 0 6px 16px rgba(100,116,139,.4); }
.inbx-sol-del:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.inbx-sol-fwd { background: linear-gradient(135deg, #60a5fa, #2563eb); box-shadow: 0 4px 12px rgba(37,99,235,.3); }
.inbx-sol-fwd:hover { box-shadow: 0 6px 16px rgba(37,99,235,.4); }
.inbx-fwd { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
.inbx-fwd + .inbx-trash { margin-left: 8px; }
.inbx-fwd:hover:not(:disabled) { background: #dbeafe; border-color: #93c5fd; }
.dark .inbx-fwd { color: #93c5fd; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.35); }
.dark .inbx-fwd:hover:not(:disabled) { background: rgba(59, 130, 246, 0.18); }
.inbx-fwd-icon { background: linear-gradient(135deg, #60a5fa, #2563eb) !important; }
.inbx-fwd-check { display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: #334155; cursor: pointer; }
.inbx-fwd-check input { margin-top: 2px; accent-color: #2563eb; }
.dark .inbx-fwd-check { color: #cbd5e1; }
.inbx-fwd-adj { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #1d4ed8; background: #eff6ff; border: 1px dashed #93c5fd; border-radius: 10px; padding: 8px 12px; }
.dark .inbx-fwd-adj { color: #93c5fd; background: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.3); }
.inbx-fwd-confirm { background: linear-gradient(135deg, #3b82f6, #2563eb) !important; box-shadow: 0 4px 12px rgba(37,99,235,.3) !important; }
.inbx-rad--fwd { border-bottom-color: #cfe2fd; background: linear-gradient(135deg, #f4f9ff 0%, #eff6ff 100%); }
.dark .inbx-rad--fwd { border-bottom-color: #1e3350; background: linear-gradient(135deg, #131e30 0%, #141c2e 100%); }
.inbx-rad--fwd .inbx-rad-ico { color: #3b82f6; }
.inbx-rad--fwd .inbx-rad-info strong { color: #1d4ed8; }
.dark .inbx-rad--fwd .inbx-rad-info strong { color: #93c5fd; }
.inbx-rad--fwd .inbx-rad-info > span { color: #2563eb; }
.dark .inbx-rad--fwd .inbx-rad-info > span { color: #7ba7f0; }
.inbx-auto { display: flex; align-items: center; gap: 7px; padding: 8px 12px; font-size: 11.5px; font-weight: 600; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; }
.inbx-auto svg { flex-shrink: 0; color: #16a34a; }
.dark .inbx-auto { color: #4ade80; background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.3); }
.inbx-dup-ver { border: none; background: none; padding: 0; color: #b45309; font-weight: 700; font-size: inherit; text-decoration: underline; cursor: pointer; }
.dark .inbx-dup-ver { color: #fcd34d; }
.inbx-dup-overlay { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(15,23,42,.45); backdrop-filter: blur(2px); }
.inbx-dup-modal { width: 100%; max-width: 420px; max-height: 85vh; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 24px 60px rgba(15,23,42,.25); padding: 18px 20px; }
.dark .inbx-dup-modal { background: #161b28; border-color: #2c3f5e; }
.inbx-dup-modal-head { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
.inbx-dup-modal-head h3 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; }
.inbx-dup-modal-head p { margin: 2px 0 0; font-size: 11.5px; color: #64748b; }
.dark .inbx-dup-modal-head h3 { color: #e2e8f0; }
.dark .inbx-dup-modal-head p { color: #94a3b8; }
.inbx-dup-chips { display: flex; gap: 8px; margin-bottom: 12px; }
.inbx-dup-est { padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 999px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.inbx-dup-est[data-e="pendiente_cama"] { background: #fef3c7; color: #b45309; border-color: #fde68a; }
.inbx-dup-est[data-e="en_espera"] { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.inbx-dup-est[data-e="completado"] { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.inbx-dup-est[data-e="negado"], .inbx-dup-est[data-e="cancelado"] { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.inbx-dup-origen { padding: 3px 10px; font-size: 11px; font-weight: 700; border-radius: 999px; background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
.dark .inbx-dup-est { background: rgba(59,130,246,.12); color: #93c5fd; border-color: rgba(59,130,246,.3); }
.dark .inbx-dup-est[data-e="pendiente_cama"] { background: rgba(245,158,11,.12); color: #fcd34d; border-color: rgba(245,158,11,.3); }
.dark .inbx-dup-est[data-e="en_espera"], .dark .inbx-dup-est[data-e="completado"] { background: rgba(34,197,94,.12); color: #4ade80; border-color: rgba(34,197,94,.3); }
.dark .inbx-dup-est[data-e="negado"], .dark .inbx-dup-est[data-e="cancelado"] { background: rgba(239,68,68,.12); color: #f87171; border-color: rgba(239,68,68,.3); }
.dark .inbx-dup-origen { background: rgba(139,92,246,.12); color: #c4b5fd; border-color: rgba(139,92,246,.3); }
.inbx-dup-datos { display: flex; flex-direction: column; gap: 7px; margin: 0; }
.inbx-dup-datos > div { display: flex; gap: 10px; font-size: 12.5px; }
.inbx-dup-datos dt { flex-shrink: 0; width: 130px; font-weight: 700; color: #64748b; }
.inbx-dup-datos dd { margin: 0; color: #0f172a; }
.dark .inbx-dup-datos dt { color: #94a3b8; }
.dark .inbx-dup-datos dd { color: #e2e8f0; }
.inbx-dup-modal-foot { display: flex; justify-content: flex-end; margin-top: 14px; }
.inbx-dup-abrir { padding: 8px 16px; font-size: 12px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 999px; text-decoration: none; box-shadow: 0 4px 12px rgba(37,99,235,.3); }
.inbx-dup-abrir:hover { filter: brightness(1.08); }

.inbx-tag-rad { display: inline-flex; align-items: center; gap: 3px; padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; white-space: nowrap; color: #047857; background: #d1fae5; border: 1px solid #a7f3d0; }
.dark .inbx-tag-rad { color: #6ee7b7; background: rgba(16,185,129,.16); border-color: rgba(52,211,153,.3); }
.inbx-tag-neg { display: inline-flex; align-items: center; gap: 3px; padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; white-space: nowrap; color: #b91c1c; background: #fee2e2; border: 1px solid #fecaca; }
.dark .inbx-tag-neg { color: #fca5a5; background: rgba(239,68,68,.16); border-color: rgba(248,113,113,.3); }

.inbx-rad { display: flex; align-items: center; gap: 10px; padding: 8px 16px; border-bottom: 1px solid #d7f3e5; background: linear-gradient(135deg, #f0fdf7 0%, #f4f8ff 100%); }
.dark .inbx-rad { border-color: #1d3a2f; background: linear-gradient(135deg, #14261f 0%, #16222f 100%); }
.inbx-rad-ico { color: #10b981; flex-shrink: 0; }
.inbx-rad-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.inbx-rad-info strong { font-size: 12px; color: #065f46; }
.dark .inbx-rad-info strong { color: #6ee7b7; }
.inbx-rad-info > span { font-size: 11px; color: #047857; }
.dark .inbx-rad-info > span { color: #5eead4; }
.inbx-rad-est { font-style: normal; text-transform: capitalize; font-weight: 600; }
.inbx-rad-link { flex-shrink: 0; font-size: 11.5px; font-weight: 700; color: #0369a1; text-decoration: none; padding: 5px 11px; border-radius: 8px; background: #fff; border: 1px solid #bae6fd; transition: background .15s ease; }
.inbx-rad-link:hover { background: #f0f9ff; }
.dark .inbx-rad-link { color: #7dd3fc; background: #1a2233; border-color: rgba(56,189,248,.3); }
.dark .inbx-rad-link:hover { background: #22304a; }

.inbx-rad--neg { border-bottom-color: #f6d5d5; background: linear-gradient(135deg, #fef6f6 0%, #fff8f8 100%); }
.dark .inbx-rad--neg { border-bottom-color: #3a2328; background: linear-gradient(135deg, #26151a 0%, #1a1622 100%); }
.inbx-rad--neg .inbx-rad-ico { color: #ef4444; }
.inbx-rad--neg .inbx-rad-info strong { color: #991b1b; }
.dark .inbx-rad--neg .inbx-rad-info strong { color: #fca5a5; }
.inbx-rad--neg .inbx-rad-info > span { color: #b91c1c; }
.dark .inbx-rad--neg .inbx-rad-info > span { color: #f8b4b4; }

.inbx-body-negar { grid-template-columns: 64px minmax(0, 1fr) minmax(360px, 460px); }
.inbx-mini { display: none; place-items: center; width: 30px; height: 30px; flex-shrink: 0; border-radius: 50%; background: linear-gradient(135deg, #16468E, #0D2D6B); color: #fff; font-size: 11px; font-weight: 800; }
.inbx-body-negar .inbx-list { align-items: center; }
.inbx-body-negar .inbx-item { width: 100%; padding: 8px 6px; align-items: center; text-align: center; }
.inbx-body-negar .inbx-mini { display: grid; }
.inbx-body-negar .inbx-item-top { justify-content: center; }
.inbx-body-negar .inbx-from,
.inbx-body-negar .inbx-item-top time,
.inbx-body-negar .inbx-subject,
.inbx-body-negar .inbx-snippet { display: none; }
.inbx-body-negar .inbx-item-meta { justify-content: center; }
.inbx-body-negar .inbx-mas,
.inbx-body-negar .inbx-vacio { width: 100%; padding: 6px 2px; font-size: 9px; }
.inbx-negar { display: flex; min-width: 0; flex-direction: column; overflow: hidden; border: 1px solid #86a5d8; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(22,70,142,.14); }
.dark .inbx-negar { border-color: rgba(255,255,255,.07); background: #161b28; box-shadow: none; }
.inbx-negar-head { position: relative; display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #edf2f9; }
.dark .inbx-negar-head { border-color: #22304a; }
.inbx-negar-icon { display: grid; place-items: center; width: 34px; height: 34px; flex-shrink: 0; border-radius: 10px; background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; box-shadow: 0 6px 14px rgba(220,38,38,.3); }
.inbx-negar-head-info h3 { margin: 0; color: #0d2d5e; font-size: 13.5px; font-weight: 800; }
.dark .inbx-negar-head-info h3 { color: #dbe7fb; }
.inbx-negar-head-info p { margin: 0; color: #94a3b8; font-size: 10.5px; }
.inbx-negar-close { margin-left: auto; padding: 4px; border: none; border-radius: 8px; background: transparent; color: #94a3b8; cursor: pointer; }
.inbx-negar-close:hover { background: #f1f5f9; color: #475569; }
.dark .inbx-negar-close:hover { background: rgba(255,255,255,.06); color: #cbd5e1; }
.inbx-negar-body { display: flex; flex: 1; flex-direction: column; gap: 10px; overflow-y: auto; padding: 12px 14px; scrollbar-width: thin; }
.inbx-negar-paciente { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 10px; background: linear-gradient(120deg, #0D2D6B, #16468E); box-shadow: 0 4px 12px rgba(13,45,107,.2); }
.inbx-negar-paciente strong { color: #fff; font-size: 12px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.inbx-negar-paciente span { color: rgba(255,255,255,.75); font-size: 10px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.inbx-negar-grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }
.inbx-negar-label { display: block; margin-bottom: 3px; color: #475569; font-size: 10px; font-weight: 700; }
.dark .inbx-negar-label { color: #cbd5e1; }
.inbx-req { color: #dc2626; }
.inbx-negar-correo { display: inline-block; max-width: 100%; padding: 4px 10px; overflow: hidden; border: 1px dashed #bfcfe8; border-radius: 8px; background: #f2f7ff; color: #315583; font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.dark .inbx-negar-correo { border-color: #314666; background: #1c2d46; color: #9dc1f7; }
.inbx-negar-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 10px 14px; border-top: 1px solid #edf2f9; }
.dark .inbx-negar-foot { border-color: #22304a; }
.inbx-adjuntos-aviso {
  display: flex; align-items: center; gap: 6px;
  margin: 0 14px 8px; padding: 8px 12px;
  font-size: 11.5px; font-weight: 600; color: #0369a1;
  background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;
}
.dark .inbx-adjuntos-aviso { color: #7dd3fc; background: rgba(14,116,144,.15); border-color: rgba(56,189,248,.3); }
.inbx-negar-cancel { padding: 7px 16px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; color: #475569; font-size: 11.5px; font-weight: 700; cursor: pointer; }
.dark .inbx-negar-cancel { border-color: rgba(255,255,255,.12); background: transparent; color: #cbd5e1; }
.inbx-negar-cancel:hover { background: #f8fafc; }
.inbx-negar-confirm { display: inline-flex; align-items: center; gap: 5px; padding: 7px 16px; border: none; border-radius: 10px; background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; font-size: 11.5px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(220,38,38,.3); }
.inbx-negar-confirm:hover { filter: brightness(1.06); }

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

.inbx-aceptar { border-color: #a7e8c4; background: linear-gradient(165deg, #f0fdf6 0%, #f6fbf9 55%, #eef6ff 100%); box-shadow: 0 8px 24px rgba(22,142,80,.14); }
.dark .inbx-aceptar { border-color: rgba(52,211,153,.18); background: linear-gradient(165deg, #12241c 0%, #161b28 55%, #14202e 100%); }
.inbx-aceptar-icon { background: linear-gradient(135deg, #22c55e, #16a34a); box-shadow: 0 6px 14px rgba(22,163,74,.3); }
.inbx-aceptar-confirm { background: linear-gradient(135deg, #22c55e, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,.3); }
.inbx-negar-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 8px; }

.inbx-form-seccion { margin: 6px 0 -2px; padding-bottom: 5px; border-bottom: 1.5px solid #e3ecfa; color: #16468e; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
.dark .inbx-form-seccion { border-color: #22304a; color: #9dc1f7; }
.inbx-aceptar .inbx-form-seccion { border-color: #c9ecd9; color: #15803d; }
.dark .inbx-aceptar .inbx-form-seccion { border-color: rgba(52,211,153,.2); color: #6ee7b7; }
.inbx-toggle { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }
.inbx-toggle button { padding: 6px 4px; border: 1.5px solid #e2e8f0; border-radius: 8px; background: #fff; color: #475569; font-size: 11px; font-weight: 700; cursor: pointer; transition: all .15s ease; }
.inbx-toggle button:hover { border-color: #93c5fd; }
.inbx-toggle button.active { border-color: #16468e; background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #16468e; }
.dark .inbx-toggle button { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); color: #cbd5e1; }
.dark .inbx-toggle button.active { border-color: #60a5fa; background: rgba(37,99,235,.15); color: #93c5fd; }
</style>
