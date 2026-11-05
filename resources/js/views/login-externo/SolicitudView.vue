<template>
  <div class="solicitud-page h-full flex flex-col overflow-hidden">

    <!-- Header compacto -->
    <div class="rf-stepper">
      <button class="rf-stepper-back" @click="router.push('/clinica')">
        <component :is="ArrowLeftIcon" class="w-3.5 h-3.5" />
      </button>

      <div class="rf-stepper-title">
        <h2>Nueva solicitud de referencia</h2>
        <p>Complete los datos del paciente y la remisión</p>
      </div>
      <div class="rf-stepper-steps">
        <div class="rf-step-pill" :class="{ done: resumenPacienteCompleto }">
          <span class="rf-step-num"><component v-if="resumenPacienteCompleto" :is="CheckCircleIcon" class="w-3 h-3" /><template v-else>1</template></span>
          Paciente
        </div>
        <span class="rf-step-line"></span>
        <div class="rf-step-pill" :class="{ done: resumenRemisionCompleto }">
          <span class="rf-step-num"><component v-if="resumenRemisionCompleto" :is="CheckCircleIcon" class="w-3 h-3" /><template v-else>2</template></span>
          Remisión
        </div>
        <span class="rf-step-line"></span>
        <div class="rf-step-pill">
          <span class="rf-step-num">3</span>
          Confirmación
        </div>
      </div>
      <div class="rf-stepper-progress">
        <div class="rf-stepper-progress-track">
          <div class="rf-stepper-progress-bar" :style="{ width: progresoFormulario + '%' }"></div>
        </div>
        <span class="rf-stepper-progress-label">{{ progresoFormulario }}%</span>
      </div>
    </div>

    <!-- Body: layout bento -->
    <div class="rf-body">
      <el-form :model="form" :rules="rules" ref="formRef" label-position="top" size="default" class="rf-form-col">

        <!-- Fila superior: Paciente + Remisión -->
        <div class="rf-bento-top">
        <!-- Datos del paciente -->
        <div class="rf-section rf-section--compact">
          <div class="rf-section-accent" style="background: linear-gradient(135deg,#2563c4,#3b82f6);"></div>
          <div class="rf-section-header">
            <div class="rf-section-icon" style="background: linear-gradient(135deg,#dbeafe,#bfdbfe); color:#2563c4;">
              <component :is="UserIcon" class="w-4 h-4" />
              <span class="rf-section-num">1</span>
            </div>
            <div class="rf-section-heading">
              <h3>Datos del paciente</h3>
              <p>Información de identificación del paciente</p>
            </div>
            <div class="rf-section-glow"></div>
          </div>
          <div class="rf-grid">
            <el-form-item label="Tipo de documento" prop="tipo_documento" required :class="['rf-field rf-col-quarter rf-stagger-1', { 'is-success': camposValidos.tipo_documento }]">
              <el-select v-model="form.tipo_documento" class="w-full" placeholder="Tipo" popper-class="rf-select-popper">
                <el-option v-for="t in TIPOS_DOCUMENTO" :key="t" :label="t" :value="t" />
              </el-select>
            </el-form-item>
            <el-form-item label="Número de documento" prop="numero_documento" required :class="['rf-field rf-col-quarter rf-stagger-2', { 'is-success': camposValidos.numero_documento }]">
              <el-input v-model="form.numero_documento" autocomplete="new-password">
                <template #prefix><component :is="IdCardIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
              </el-input>
            </el-form-item>
            <el-form-item label="Fecha de nacimiento" prop="fecha_nacimiento" required :class="['rf-field rf-col-quarter rf-stagger-3', { 'is-success': camposValidos.fecha_nacimiento }]">
              <el-date-picker
                v-model="form.fecha_nacimiento"
                type="date"
                value-format="YYYY-MM-DD"
                placeholder="AAAA-MM-DD"
                class="w-full"
                :disabled-date="(d: Date) => d.getTime() > Date.now()"
                popper-class="rf-select-popper"
              />
            </el-form-item>
            <el-form-item label="Género" prop="genero" required :class="['rf-field rf-col-quarter rf-stagger-4', { 'is-success': camposValidos.genero }]">
              <div class="rf-gestante-btns">
                <button type="button" class="rf-gestante-btn" :class="{ active: form.genero === 'M' }" @click="seleccionarGenero('M')">Masculino</button>
                <button type="button" class="rf-gestante-btn" :class="{ active: form.genero === 'F' }" @click="seleccionarGenero('F')">Femenino</button>
              </div>
            </el-form-item>
            <el-form-item label="EPS / Aseguradora" prop="eps" required :class="['rf-field rf-col-2 rf-stagger-5', { 'is-success': camposValidos.eps }]">
              <el-select v-model="form.eps" filterable class="w-full" placeholder="Seleccione o escriba" popper-class="rf-select-popper">
                <el-option v-for="e in EPS_LIST" :key="e" :label="e" :value="e" />
              </el-select>
            </el-form-item>
            <el-form-item label="Edad" prop="edad" required :class="['rf-field rf-col-quarter rf-stagger-6', { 'is-success': camposValidos.edad }]">
              <el-input :model-value="form.edad !== null ? `${form.edad} años` : ''" placeholder="Auto" readonly>
                <template #suffix><component :is="CalendarIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
              </el-input>
            </el-form-item>
            <el-form-item label="Teléfono del paciente" :class="['rf-field rf-col-quarter rf-stagger-4', { 'is-success': camposValidos.telefono_paciente }]">
              <el-input v-model="form.telefono_paciente" autocomplete="new-password" maxlength="15" @input="soloNumeros('telefono_paciente')">
                <template #prefix><component :is="PhoneIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
              </el-input>
            </el-form-item>
            <div class="rf-grid-trio">
              <el-form-item label="Nombres" prop="primer_nombre" required :class="['rf-field rf-stagger-6', { 'is-success': camposValidos.primer_nombre }]">
                <el-input v-model="nombres" autocomplete="new-password" placeholder="Nombres del paciente" @input="capitalizarTexto(nombres)" />
              </el-form-item>
              <el-form-item label="Apellidos" prop="primer_apellido" required :class="['rf-field rf-stagger-7', { 'is-success': camposValidos.primer_apellido }]">
                <el-input v-model="apellidos" autocomplete="new-password" placeholder="Apellidos del paciente" @input="capitalizarTexto(apellidos)" />
              </el-form-item>
              <el-form-item label="Municipio" prop="municipio_capita" required :class="['rf-field rf-stagger-8', { 'is-success': camposValidos.municipio_capita }]">
                <el-select-v2
                  v-model="form.municipio_capita"
                  :options="municipiosOpciones"
                  filterable
                  class="w-full"
                  placeholder="Seleccione el municipio"
                  popper-class="rf-select-popper"
                  :popper-options="{ strategy: 'fixed' }"
                >
                  <template #prefix><component :is="MapPinIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
                </el-select-v2>
              </el-form-item>
            </div>
            <el-form-item label="Dirección del paciente" :class="['rf-field rf-col-3-fix rf-stagger-5', { 'is-success': camposValidos.direccion_paciente }]">
              <el-input v-model="form.direccion_paciente" autocomplete="new-password" maxlength="25" show-word-limit />
            </el-form-item>
            <el-form-item
              prop="urgencia_vital"
              required
              :class="['rf-field rf-col-quarter rf-urgencia-vital-field', { 'rf-urgencia-vital-field--active': form.urgencia_vital === true }]"
              title="Marque Sí únicamente cuando la vida del paciente esté en riesgo inmediato."
            >
              <template #label>
                <span class="rf-urgencia-vital-label"><span>!</span>Urgencia vital</span>
              </template>
              <div class="rf-gestante-btns rf-urgencia-vital-btns">
                <button type="button" class="rf-gestante-btn" :class="{ active: form.urgencia_vital === false }" @click="form.urgencia_vital = false">No</button>
                <button
                  type="button"
                  class="rf-gestante-btn"
                  :class="{ active: form.urgencia_vital === true, 'rf-urgencia-vital-yes-active': form.urgencia_vital === true }"
                  @click="form.urgencia_vital = true"
                >Sí</button>
              </div>
            </el-form-item>
            <el-form-item label="Historia clínica" prop="resumen_historia_clinica" required :class="['rf-field rf-col-full rf-stagger-6', { 'is-success': camposValidos.resumen_historia_clinica }]">
              <div class="rf-hc-field">
                <el-input v-model="form.resumen_historia_clinica" type="textarea" :rows="3"
                  placeholder="Motivo de remisión, antecedentes, estado actual del paciente..." autocomplete="new-password" @input="capitalizar('resumen_historia_clinica')" />
                <button type="button" class="rf-hc-ver-mas" @click="showHcModal = true">Ver más</button>
              </div>
            </el-form-item>
          </div>

          <!-- Diagnósticos -->
          <div class="rf-subsection">
            <div class="rf-subsection-header">
              <div class="rf-subsection-icon rf-sub-icon-amber">
                <component :is="ClipboardListIcon" class="w-3.5 h-3.5" />
              </div>
              <h4>Diagnósticos <span class="rf-req">*</span></h4>
              <span class="rf-subsection-note">El primero es el principal, los demás son secundarios</span>
            </div>
            <div class="rf-dx-section">
              <button type="button" class="rf-attach-btn" @click="showDxModal = true">
                <component :is="ClipboardListIcon" class="w-4 h-4" />
                <span>{{ conteoDx ? 'Gestionar diagnósticos' : 'Agregar diagnósticos' }}</span>
                <span v-if="conteoDx" class="rf-attach-count">{{ conteoDx }}</span>
              </button>
              <p v-if="conteoDx" class="rf-dx-preview"><strong>Principal:</strong> {{ diagnosticoPrincipalTexto }}</p>
              <p v-else class="rf-dx-empty">Agregue al menos un diagnóstico.</p>
            </div>
          </div>
        </div>

        <!-- Datos de la remisión -->
        <div class="rf-section rf-section--compact">
          <div class="rf-section-accent" style="background: linear-gradient(135deg,#15966a,#22c55e);"></div>
          <div class="rf-section-header">
            <div class="rf-section-icon" style="background: linear-gradient(135deg,#dcfce7,#bbf7d0); color:#15966a;">
              <component :is="StethoscopeIcon" class="w-4 h-4" />
              <span class="rf-section-num" style="background:#15966a;">2</span>
            </div>
            <div class="rf-section-heading">
              <h3>Datos de la remisión</h3>
              <p>Detalle clínico y destino de la solicitud</p>
            </div>
            <div class="rf-section-glow" style="background: radial-gradient(circle, rgba(21,150,106,.08), transparent 70%);"></div>
          </div>

          <div class="rf-subsection rf-subsection-first">
            <div class="rf-grid">
              <el-form-item label="Institución que refiere (IPS o EPS)" prop="institucion_remitente" required :class="['rf-field rf-col-2 rf-stagger-1', { 'is-success': camposValidos.institucion_remitente }]">
                <el-select
                  v-model="form.clinica_id"
                  filterable
                  class="w-full"
                  placeholder="Seleccione la institución"
                  popper-class="rf-select-popper"
                  :popper-options="{ strategy: 'fixed' }"
                  @change="onClinicaSelect"
                >
                  <template #prefix><component :is="BuildingIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
                  <el-option key="__nueva_clinica" label="➕ Registrar institución que refiere..." value="__nueva_clinica" />
                  <el-option v-for="c in clinicasOpciones" :key="c.id" :label="c.nombre" :value="c.id" />
                </el-select>
              </el-form-item>
              <el-form-item label="Especialidad requerida" prop="especialidad_requerida" required :class="['rf-field rf-col-2 rf-stagger-3', { 'is-success': camposValidos.especialidad_requerida }]">
                <el-select v-model="form.especialidad_requerida" filterable class="w-full" placeholder="Seleccione" popper-class="rf-select-popper" @change="onEspecialidadSelect">
                  <el-option key="__otra_esp" label="➕ Otra especialidad..." value="__otra_esp" />
                  <el-option v-for="e in ESPECIALIDADES" :key="e" :label="e" :value="e" />
                </el-select>
              </el-form-item>
              <el-form-item label="Tipo de referencia" prop="tipo_referencia" required :class="['rf-field rf-col-2 rf-stagger-4', { 'is-success': camposValidos.tipo_referencia }]">
                <el-select v-model="form.tipo_referencia" class="w-full" placeholder="Seleccione" popper-class="rf-select-popper">
                  <el-option label="Referencia redonda" value="Referencia redonda" />
                  <el-option label="Referencia integral" value="Referencia integral" />
                </el-select>
              </el-form-item>
              <el-form-item label="Servicio / Ubicación actual" prop="servicio_ubicacion_actual" required :class="['rf-field rf-col-2 rf-stagger-5', { 'is-success': camposValidos.servicio_ubicacion_actual }]">
                <el-select v-model="form.servicio_ubicacion_actual" class="w-full" placeholder="Seleccione" popper-class="rf-select-popper">
                  <el-option v-for="s in SERVICIOS" :key="s" :label="s" :value="s" />
                </el-select>
              </el-form-item>
              <el-form-item label="¿Requiere aislamiento?" :class="['rf-field rf-col-quarter rf-stagger-6', { 'is-success': camposValidos.requiere_aislamiento }]">
                <div class="rf-gestante-btns">
                  <button type="button" class="rf-gestante-btn" :class="{ active: form.requiere_aislamiento === true }" @click="form.requiere_aislamiento = true">Sí</button>
                  <button type="button" class="rf-gestante-btn" :class="{ active: form.requiere_aislamiento === false }" @click="form.requiere_aislamiento = false">No</button>
                </div>
              </el-form-item>
              <el-form-item v-if="form.requiere_aislamiento === true" label="Tipo de aislamiento" :class="['rf-field rf-col-quarter rf-stagger-6', { 'is-success': camposValidos.tipo_aislamiento }]">
                <el-select v-model="form.tipo_aislamiento" class="w-full" placeholder="Seleccione" popper-class="rf-select-popper">
                  <el-option label="Contacto" value="contacto" />
                  <el-option label="Respiratorio" value="respiratorio" />
                  <el-option label="Cohortizado" value="cohortizado" />
                  <el-option label="Vectores" value="vectores" />
                </el-select>
              </el-form-item>
              <el-form-item label="¿Paciente gestante?" :class="['rf-field rf-col-quarter rf-stagger-7', { 'is-success': camposValidos.gestante }]">
                <div class="rf-gestante-btns">
                  <button type="button" class="rf-gestante-btn" :class="{ active: form.gestante === true }" @click="form.gestante = true">Sí</button>
                  <button type="button" class="rf-gestante-btn" :class="{ active: form.gestante === false }" @click="form.gestante = false">No</button>
                </div>
              </el-form-item>
              <el-form-item label="Condición especial" :class="['rf-field rf-col-quarter rf-stagger-8', { 'is-success': camposValidos.condicion_especial }]">
                <el-select v-model="form.condicion_especial" class="w-full" placeholder="Seleccione" clearable popper-class="rf-select-popper">
                  <el-option label="Discapacidad visual" value="Discapacidad visual" />
                  <el-option label="Discapacidad auditiva" value="Discapacidad auditiva" />
                  <el-option label="Discapacidad mental" value="Discapacidad mental" />
                  <el-option label="Discapacidad física" value="Discapacidad física" />
                </el-select>
              </el-form-item>
              <el-form-item label="Quien remite" prop="quien_remitente" class="rf-field rf-col-2 rf-stagger-9">
                <el-input v-model="form.quien_remitente" placeholder="Nombre de quien remite" autocomplete="new-password" @input="capitalizar('quien_remitente')">
                  <template #prefix><component :is="UserIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
                </el-input>
              </el-form-item>
              <el-form-item label="Teléfono de contacto" prop="telefono_contacto" class="rf-field rf-col-2 rf-stagger-10">
                <el-input v-model="form.telefono_contacto" placeholder="Teléfono" autocomplete="new-password">
                  <template #prefix><component :is="PhoneIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
                </el-input>
              </el-form-item>
            </div>
            <div class="rf-attach-compact" :class="{ 'rf-attach-active': dragOver }" @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop">
              <div class="rf-attach-drop" @click="fileInput?.click()">
                <component :is="PaperclipIcon" class="w-5 h-5" />
                <p>Adjuntar soportes — arrastre archivos aquí o haga clic</p>
                <small>Opcional · máx. {{ MAX_ARCHIVOS }} archivos · PDF, imágenes, Word, Excel... {{ MAX_ARCHIVO_MB }}MB c/u</small>
                <span v-if="adjuntos.length" class="rf-attach-count">{{ adjuntos.length }}</span>
              </div>
              <input ref="fileInput" class="hidden" type="file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tiff,.svg,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar" multiple @change="seleccionarAdjuntos" />
              <transition-group v-if="adjuntos.length" name="file-list" tag="div" class="rf-file-mini-grid">
                <div v-for="(archivo, i) in adjuntos" :key="archivo.name + i" class="rf-file-mini-item">
                  <img v-if="esImagen(archivo)" :src="previewUrl(archivo)" class="rf-file-mini-thumb" :alt="archivo.name" @click.stop="abrirLightbox(archivo)" />
                  <span v-else class="rf-file-mini-badge rf-file-mini-badge-click" :class="getFileTypeClass(archivo)" title="Abrir archivo" @click.stop="abrirArchivo(archivo)">{{ getFileTypeLabel(archivo) }}</span>
                  <span class="rf-file-mini-name" :title="archivo.name">{{ archivo.name }}</span>
                  <button type="button" class="rf-file-mini-remove" @click.stop="removerAdjunto(i)">
                    <component :is="XCircleIcon" class="w-3 h-3" />
                  </button>
                </div>
              </transition-group>
            </div>
          </div>
          <div class="rf-card-actions">
            <el-button class="rf-btn-back" @click="limpiarFormulario">Cancelar</el-button>
            <el-button type="primary" class="rf-btn-submit" @click="abrirConfirmacion">Enviar solicitud</el-button>
          </div>
        </div>
        </div>

      </el-form>
    </div>

    <!-- Dialog de confirmación -->
    <el-dialog
      v-model="showConfirmResumen"
      width="980px"
      class="confirm-dialog"
      :close-on-click-modal="false"
      :show-close="false"
      append-to-body
      align-center
    >
      <div class="confirm-body">
        <button type="button" class="confirm-close-btn" @click="showConfirmResumen = false">
          <component :is="XIcon" class="w-4 h-4" />
        </button>

        <div class="confirm-head">
          <div class="confirm-head-icon">
            <component :is="ClipboardListIcon" class="w-5 h-5" />
          </div>
          <div class="confirm-head-info">
            <p class="confirm-head-title">Confirmar envío de solicitud</p>
            <p class="confirm-head-sub">Revisa el resumen de la solicitud antes de enviarla.</p>
          </div>
          <span class="confirm-head-badge">Paso final</span>
        </div>

        <div class="confirm-main">
          <!-- Una sola cuadrícula mantiene alineadas las columnas hasta la historia clínica -->
            <!-- Paciente -->
    <div class="confirm-card confirm-c-paciente">
              <div class="confirm-card-head">
                <span class="confirm-card-icon ci-blue"><component :is="UserIcon" class="w-3.5 h-3.5" /></span>
                <p class="confirm-card-title ct-blue">Paciente</p>
              </div>
              <div class="confirm-card-body">
                <div class="confirm-data-row"><span>Nombre</span><strong>{{ nombreCompleto }}</strong></div>
                <div class="confirm-data-row"><span>Documento</span><strong>{{ form.tipo_documento }} {{ form.numero_documento }}</strong></div>
                <div class="confirm-data-row"><span>Edad / Género</span><strong>{{ form.edad ?? '—' }} años · {{ form.genero === 'M' ? 'Masc.' : form.genero === 'F' ? 'Fem.' : '—' }}</strong></div>
                <div class="confirm-data-row"><span>EPS</span><strong>{{ form.eps || '—' }}</strong></div>
                <div class="confirm-data-row"><span>Municipio</span><strong>{{ form.municipio_capita || '—' }}</strong></div>
                <div v-if="form.direccion_paciente" class="confirm-data-row"><span>Dirección</span><strong>{{ form.direccion_paciente }}</strong></div>
                <div v-if="form.telefono_paciente" class="confirm-data-row"><span>Teléfono</span><strong>{{ form.telefono_paciente }}</strong></div>
              </div>
            </div>

            <!-- Remisión -->
            <div class="confirm-card confirm-c-remision">
              <div class="confirm-card-head">
                <span class="confirm-card-icon ci-amber"><component :is="BuildingIcon" class="w-3.5 h-3.5" /></span>
                <p class="confirm-card-title ct-amber">Remisión</p>
              </div>
              <div class="confirm-card-body">
                <div class="confirm-data-row"><span>Fecha solicitud</span><strong>{{ fechaSolicitudTexto }}</strong></div>
                <div class="confirm-data-row"><span>Institución</span><strong>{{ form.institucion_remitente || '—' }}</strong></div>
                <div class="confirm-data-row"><span>Quien remite</span><strong>{{ form.quien_remitente || '—' }}</strong></div>
                <div class="confirm-data-row"><span>Teléfono</span><strong>{{ form.telefono_contacto || '—' }}</strong></div>
                <div class="confirm-data-row"><span>Especialidad</span><strong>{{ form.especialidad_requerida || '—' }}</strong></div>
                <div class="confirm-data-row"><span>Tipo de referencia</span><strong>{{ form.tipo_referencia || '—' }}</strong></div>
                <div class="confirm-data-row"><span>Servicio actual</span><strong>{{ form.servicio_ubicacion_actual || '—' }}</strong></div>
              </div>
            </div>

            <!-- Diagnósticos -->
            <div class="confirm-card confirm-c-dx">
              <div class="confirm-card-head">
                <span class="confirm-card-icon ci-violet"><component :is="ClipboardListIcon" class="w-3.5 h-3.5" /></span>
                <p class="confirm-card-title ct-violet">Diagnósticos</p>
              </div>
              <div class="confirm-card-body">
                <div v-for="(dx, i) in diagnosticosValidos" :key="i" class="confirm-dx-item">
                  <strong v-if="dx.codigo_cie10" class="confirm-dx-code">{{ dx.codigo_cie10 }}</strong>
                  <span class="confirm-dx-desc">
                    <em v-if="i === 0" class="confirm-dx-principal">Principal</em>
                    {{ dxTexto(dx) }}
                  </span>
                </div>
                <p v-if="!diagnosticosValidos.length" class="confirm-empty">—</p>
              </div>
            </div>

            <!-- Historia clínica -->
            <div class="confirm-card confirm-hc-card confirm-c-hc">
              <div class="confirm-card-head">
                <span class="confirm-card-icon ci-blue"><component :is="FileTextIcon" class="w-3.5 h-3.5" /></span>
                <p class="confirm-card-title ct-blue">Historia clínica</p>
              </div>
              <div class="confirm-card-body confirm-hc-body">
                <div class="confirm-hc-box">{{ form.resumen_historia_clinica || '—' }}</div>
              </div>
            </div>

            <!-- Condiciones + Adjuntos -->
            <div class="confirm-side-col confirm-c-side">
              <div class="confirm-card">
                <div class="confirm-card-head">
                  <span class="confirm-card-icon ci-slate"><component :is="StethoscopeIcon" class="w-3.5 h-3.5" /></span>
                  <p class="confirm-card-title ct-slate">Condiciones</p>
                </div>
                <div class="confirm-card-body">
                  <div class="confirm-data-row">
                    <span>Urgencia vital</span>
                    <strong :class="{ 'confirm-vital-value': form.urgencia_vital }">{{ form.urgencia_vital ? 'Sí' : 'No' }}</strong>
                  </div>
                  <div class="confirm-data-row"><span>Aislamiento</span><strong>{{ form.requiere_aislamiento ? ('Sí' + (form.tipo_aislamiento ? ' - ' + form.tipo_aislamiento : '')) : 'No' }}</strong></div>
                  <div class="confirm-data-row"><span>Gestante</span><strong>{{ form.gestante ? 'Sí' : 'No' }}</strong></div>
                  <div v-if="form.condicion_especial" class="confirm-data-row"><span>Condición especial</span><strong>{{ form.condicion_especial }}</strong></div>
                  <div v-if="form.observaciones" class="confirm-data-row"><span>Observaciones</span><strong>{{ form.observaciones }}</strong></div>
                </div>
              </div>
              <div class="confirm-card">
                <div class="confirm-card-head">
                  <span class="confirm-card-icon ci-rose"><component :is="PaperclipIcon" class="w-3.5 h-3.5" /></span>
                  <p class="confirm-card-title ct-rose">Adjuntos ({{ adjuntos.length }})</p>
                </div>
                <div class="confirm-card-body">
                  <div v-if="adjuntos.length" class="confirm-adjuntos-row">
                    <div v-for="(archivo, i) in adjuntos" :key="archivo.name + i" class="confirm-adjunto-chip">
                      <img v-if="esImagen(archivo)" :src="previewUrl(archivo)" class="confirm-adjunto-thumb-sm" :alt="archivo.name" @click="abrirLightbox(archivo)" />
                      <div v-else class="confirm-adjunto-badge-sm" :class="getFileTypeClass(archivo)">{{ getFileTypeLabel(archivo) }}</div>
                      <span class="confirm-adjunto-name-sm" :title="archivo.name">{{ archivo.name }}</span>
                    </div>
                  </div>
                  <p v-else class="confirm-empty">Sin archivos adjuntos</p>
                </div>
              </div>
            </div>
        </div>
      </div>

      <template #footer>
        <div class="confirm-footer">
          <p class="confirm-footer-note">
            <component :is="ShieldIcon" class="w-3.5 h-3.5" />
            Tu información está protegida y será tratada confidencialmente.
          </p>
          <el-button class="rf-btn-back" @click="showConfirmResumen = false">
            <component :is="XIcon" class="w-3.5 h-3.5 mr-1" /> Cancelar
          </el-button>
          <el-button type="primary" class="rf-btn-submit confirm-submit-btn" :loading="guardando" @click="guardar">
            <component :is="SendIcon" class="w-3.5 h-3.5 mr-1" /> Confirmar y enviar
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- Modal: datos de la clínica que refiere -->
    <el-dialog
      v-model="showClinicaModal"
      width="560px"
      class="confirm-dialog clinica-dialog"
      :close-on-click-modal="false"
      :show-close="false"
      append-to-body
      align-center
    >
      <div class="confirm-body">
        <button type="button" class="confirm-close-btn" @click="showClinicaModal = false">
          <component :is="XIcon" class="w-4 h-4" />
        </button>

        <div class="confirm-hero">
          <div class="confirm-icon-wrap clinica-icon-wrap">
            <component :is="BuildingIcon" class="w-6 h-6" />
          </div>
          <div class="confirm-hero-text">
            <h3 class="confirm-title">Datos de la institución que refiere</h3>
            <p class="confirm-subtitle">Regístrela una sola vez — quedará en la lista y las respuestas llegarán al correo que indique.</p>
          </div>
        </div>

        <div class="clinica-form">
          <p class="clinica-section"><span class="clinica-section-bar clinica-bar-blue"></span>Identificación</p>
          <label class="clinica-field clinica-field-full">
            <span>Institución que refiere <em>*</em></span>
            <el-input v-model="nuevaClinica.nombre" placeholder="Nombre de la clínica, EPS o IPS" autocomplete="new-password">
              <template #prefix><component :is="BuildingIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
            </el-input>
          </label>
          <label class="clinica-field">
            <span>Municipio o ciudad <em>*</em></span>
            <el-select-v2
              v-model="nuevaClinica.municipio"
              :options="municipiosOpciones"
              filterable
              class="w-full"
              placeholder="Seleccione el municipio"
              popper-class="rf-select-popper"
              :popper-options="{ strategy: 'fixed' }"
            >
              <template #prefix><component :is="MapPinIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
            </el-select-v2>
          </label>
          <label class="clinica-field">
            <span>Dirección</span>
            <el-input v-model="nuevaClinica.direccion" placeholder="Dirección de la sede" autocomplete="new-password">
              <template #prefix><component :is="MapPinIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
            </el-input>
          </label>

          <p class="clinica-section"><span class="clinica-section-bar clinica-bar-green"></span>Contacto y notificaciones</p>
          <label class="clinica-field clinica-field-full">
            <span>Correo electrónico <em>*</em></span>
            <el-input v-model="nuevaClinica.email" type="email" placeholder="correo@institucion.com" autocomplete="new-password">
              <template #prefix><component :is="MailIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
            </el-input>
            <small class="clinica-hint">
              <component :is="ShieldIcon" class="w-3 h-3" />
              A este correo llegan la aceptación, el código de referencia y cada cambio de estado.
            </small>
          </label>
          <label class="clinica-field">
            <span>Persona a cargo</span>
            <el-input v-model="nuevaClinica.persona_cargo" placeholder="Nombre del responsable" autocomplete="new-password">
              <template #prefix><component :is="UserIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
            </el-input>
          </label>
          <label class="clinica-field">
            <span>Número telefónico</span>
            <el-input v-model="nuevaClinica.telefono" placeholder="Teléfono" autocomplete="new-password">
              <template #prefix><component :is="PhoneIcon" class="w-3.5 h-3.5 rf-input-icon" /></template>
            </el-input>
          </label>
        </div>
      </div>

      <template #footer>
        <div class="confirm-footer">
          <el-button class="rf-btn-back" @click="showClinicaModal = false">
            <component :is="XIcon" class="w-3.5 h-3.5 mr-1" /> Cancelar
          </el-button>
          <p class="confirm-footer-note">
            <component :is="BuildingIcon" class="w-3.5 h-3.5" />
            Queda disponible en la lista al instante
          </p>
          <el-button type="primary" class="rf-btn-submit" :loading="guardandoClinica" @click="guardarClinica">
            <component :is="BuildingIcon" class="w-3.5 h-3.5 mr-1" /> Registrar institución
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- Modal de diagnósticos -->
    <el-dialog v-model="showDxModal" width="620px" class="dx-dialog" :close-on-click-modal="false" :show-close="false" append-to-body align-center>
      <div class="dx-body">
        <button type="button" class="confirm-close-btn" @click="showDxModal = false">
          <component :is="XIcon" class="w-4 h-4" />
        </button>
        <div class="dx-head">
          <div class="dx-head-icon">
            <component :is="ClipboardListIcon" class="w-4 h-4" />
          </div>
          <div>
            <h3>Diagnósticos <span class="rf-req">*</span></h3>
            <p>El primero es el principal, los demás son secundarios</p>
          </div>
        </div>
        <div ref="dxListRef" class="rf-dx-list dx-modal-list">
          <div v-for="(dx, i) in diagnosticos" :key="i" class="rf-dx-row-block">
            <p class="rf-dx-row-tag" :class="{ 'rf-dx-row-tag--sec': i > 0 }">
              {{ i === 0 ? 'Diagnóstico principal' : `Diagnóstico secundario ${i}` }}
            </p>
            <div class="rf-dx-inline-row">
              <el-autocomplete
                v-model="dx.descripcion"
                class="w-full"
                placeholder="Escriba el diagnóstico o síntoma libremente..."
                popper-class="rf-select-popper"
                :fetch-suggestions="buscarSugerenciasCIE10"
                @select="(item: any) => seleccionarSugerenciaCIE10(i, item)"
                @blur="verificarCodigoCIE10(i)"
              >
                <template #default="{ item }">
                  <span class="rf-dx-suggestion-code">{{ item.codigo }}</span>
                  <span class="rf-dx-suggestion-desc">{{ item.descripcion }}</span>
                </template>
              </el-autocomplete>
              <button v-if="diagnosticos.length > 1" type="button" class="rf-dx-remove" @click="quitarDiagnostico(i)">
                <component :is="XIcon" class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
          <button type="button" class="rf-attach-btn rf-add-dx-btn" @click="agregarDiagnostico">
            <component :is="PlusIcon" class="w-4 h-4" />
            <span>Agregar diagnóstico secundario</span>
          </button>
          <p v-if="diagnosticos.length === 0" class="rf-dx-empty">Agregue al menos un diagnóstico.</p>
        </div>
        <div class="dx-foot">
          <el-button type="primary" class="rf-btn-submit" @click="showDxModal = false">Listo</el-button>
        </div>
      </div>
    </el-dialog>

    <!-- Modal de historia clínica -->
    <el-dialog v-model="showHcModal" width="640px" class="dx-dialog" :close-on-click-modal="false" :show-close="false" append-to-body align-center>
      <div class="dx-body">
        <button type="button" class="confirm-close-btn" @click="showHcModal = false">
          <component :is="XIcon" class="w-4 h-4" />
        </button>
        <div class="dx-head">
          <div class="dx-head-icon dx-head-icon--violet">
            <component :is="FileTextIcon" class="w-4 h-4" />
          </div>
          <div>
            <h3>Historia clínica <span class="rf-req">*</span></h3>
            <p>Motivo de remisión, antecedentes y estado actual del paciente</p>
          </div>
        </div>
        <el-input v-model="form.resumen_historia_clinica" type="textarea" :rows="11" class="hc-modal-textarea"
          placeholder="Motivo de remisión, antecedentes, estado actual del paciente..." autocomplete="new-password" @input="capitalizar('resumen_historia_clinica')" />
        <div class="dx-foot">
          <el-button type="primary" class="rf-btn-submit" @click="showHcModal = false">Listo</el-button>
        </div>
      </div>
    </el-dialog>

    <!-- Modal éxito -->
    <el-dialog v-model="modalExito" width="480px" class="exito-dialog" :show-close="false" align-center @close="onCerrarExito">
      <div class="exito-content">
        <div class="exito-confetti">
          <span v-for="i in 12" :key="i" class="confetti-piece" :style="{ '--i': i }"></span>
        </div>
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
        <div class="exito-actions">
          <el-button class="exito-btn-secondary" @click="modalExito = false">
            <component :is="PlusIcon" class="w-4 h-4 mr-1" /> Nueva solicitud
          </el-button>
          <el-button type="primary" class="exito-btn !bg-[#0D2D6B] flex-1" @click="router.push('/clinica')">
            Ir al inicio
          </el-button>
        </div>
      </div>
    </el-dialog>

    <!-- Lightbox: vista ampliada de imagen adjunta -->
    <Teleport to="body">
      <transition name="fade-scale">
        <div v-if="lightboxSrc" class="rf-lightbox" @click="cerrarLightbox">
          <button type="button" class="rf-lightbox-close" @click.stop="cerrarLightbox">
            <component :is="XIcon" class="w-4 h-4" />
          </button>
          <img :src="lightboxSrc" :alt="lightboxName" class="rf-lightbox-img" @click.stop />
          <p class="rf-lightbox-name">{{ lightboxName }}</p>
        </div>
      </transition>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, type Ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessageBox } from 'element-plus';
import notify from '@/plugins/toast';
import {
  Plus as PlusIcon,
  ArrowLeft as ArrowLeftIcon,
  X as XIcon,
  XCircle as XCircleIcon,
  CheckCircle as CheckCircleIcon,
  Paperclip as PaperclipIcon,
  Stethoscope as StethoscopeIcon,
  User as UserIcon,
  UserCheck as UserCheckIcon,
  ClipboardList as ClipboardListIcon,
  FileText as FileTextIcon,
  IdCard as IdCardIcon,
  Phone as PhoneIcon,
  MapPin as MapPinIcon,
  Shield as ShieldIcon,
  Calendar as CalendarIcon,
  Repeat as RepeatIcon,
   Send as SendIcon,
  Building2 as BuildingIcon,
  Mail as MailIcon,
} from '@lucide/vue';
import http from '@/plugins/axios';
import { TIPOS_DOCUMENTO, EPS_LIST, ESPECIALIDADES, SERVICIOS } from '@/data/referencia';

const router = useRouter();
const guardando = ref(false);
const showConfirmResumen = ref(false);
const showDxModal = ref(false);
const showHcModal = ref(false);
const fechaSolicitud = ref<Date | null>(null);
const formRef = ref();
const adjuntos = ref<File[]>([]);
const dragOver = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

interface DiagnosticoItem {
  codigo_cie10: string;
  descripcion: string;
}

const diagnosticos = ref<DiagnosticoItem[]>([{ codigo_cie10: '', descripcion: '' }]);
const dxListRef = ref<HTMLElement | null>(null);

function agregarDiagnostico() {
  diagnosticos.value.push({ codigo_cie10: '', descripcion: '' });
  nextTick(() => dxListRef.value?.scrollTo({ top: dxListRef.value.scrollHeight, behavior: 'smooth' }));
}

function seleccionarGenero(valor: 'M' | 'F') {
  form.value.genero = valor;
  formRef.value?.validateField('genero');
}

function quitarDiagnostico(i: number) {
  diagnosticos.value.splice(i, 1);
}

/** Catálogo completo de municipios para la lista desplegable de "Municipio"
 * (~1.100 municipios de Colombia, importado en la tabla ciudades_gomedisys
 * desde la propia tabla generalPoliticalDivisions de Gomedisys) — se carga
 * una sola vez al abrir el formulario. Solo se puede elegir de esta lista
 * (no texto libre), así el valor que se envía siempre viene en el formato
 * exacto "Municipio, Departamento" que Gomedisys reconoce. */
const municipiosOpciones = ref<{ value: string; label: string }[]>([]);

/** Clínicas registradas para el desplegable "Institución que refiere".
 * Se cargan una vez al abrir el formulario; al elegir una, la solicitud
 * queda vinculada (clinica_id) y sus notificaciones van al correo que la
 * clínica registró. */
interface ClinicaOpcion { id: number; nombre: string; ciudad: string | null; departamento: string | null }
const clinicasOpciones = ref<ClinicaOpcion[]>([]);

async function cargarClinicas() {
  try {
    const { data } = await http.get('/api/externo/clinicas');
    clinicasOpciones.value = data.data as ClinicaOpcion[];
  } catch {
    clinicasOpciones.value = [];
  }
}

/** Modal "Datos de la institución que refiere" — registro rápido desde el
 * formulario (puede ser clínica, EPS o IPS). Al guardar, la institución
 * entra al desplegable y al módulo interno de clínicas como pendiente. */
const showClinicaModal = ref(false);
const guardandoClinica = ref(false);
const nuevaClinica = ref({
  nombre: '', municipio: '', direccion: '', email: '', persona_cargo: '', telefono: '',
});

/** Última clínica real elegida — si el usuario abre "Registrar institución"
 * y cierra el modal sin guardar, la selección previa se restaura en vez
 * de quedar borrada. */
let ultimaClinicaValida: number | string | null = null;

function onClinicaSelect(val: number | string) {
  if (val === '__nueva_clinica') {
    form.value.clinica_id = ultimaClinicaValida;
    nuevaClinica.value = { nombre: '', municipio: '', direccion: '', email: '', persona_cargo: '', telefono: '' };
    showClinicaModal.value = true;
    return;
  }
  ultimaClinicaValida = val;
  const clinica = clinicasOpciones.value.find(c => c.id === val);
  if (clinica) {
    form.value.institucion_remitente = clinica.nombre;
    formRef.value?.validateField('institucion_remitente');
  }
}

async function guardarClinica() {
  const c = nuevaClinica.value;
  if (!c.nombre.trim()) { notify.error('Indique el nombre de la institución.'); return; }
  if (!c.municipio) { notify.error('Seleccione el municipio de la institución.'); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.email.trim())) { notify.error('Indique un correo electrónico válido — a ese correo llegarán las respuestas.'); return; }

  guardandoClinica.value = true;
  try {
    const { data } = await http.post('/api/externo/clinicas', c);
    const clinica = data.data as ClinicaOpcion;
    if (!clinicasOpciones.value.some(x => x.id === clinica.id)) {
      clinicasOpciones.value = [...clinicasOpciones.value, clinica].sort((a, b) => a.nombre.localeCompare(b.nombre));
    }
    form.value.clinica_id = clinica.id;
    ultimaClinicaValida = clinica.id;
    form.value.institucion_remitente = clinica.nombre;
    formRef.value?.validateField('institucion_remitente');
    showClinicaModal.value = false;
    notify.success('Institución registrada — las respuestas llegarán al correo indicado.');
  } catch (e: any) {
    notify.error(e.response?.data?.message || 'No se pudo registrar la institución. Intente de nuevo.');
  } finally {
    guardandoClinica.value = false;
  }
}

async function cargarMunicipios() {
  try {
    const { data } = await http.get('/api/externo/ciudades-gomedisys');
    municipiosOpciones.value = (data.data as { nombre: string }[]).map(c => ({ value: c.nombre, label: c.nombre }));
  } catch {
    municipiosOpciones.value = [];
  }
}

/** Sugerencias CIE-10 para el autocompletado del diagnóstico. El campo
 * siempre acepta texto libre (v-model directo) — esto solo ofrece atajos.
 * Busca contra el catálogo real de Gomedisys (~12.500 códigos específicos,
 * importado en la tabla diagnosticos_cie10) en vez de una lista fija en el
 * cliente — así el código que se elija siempre existe allá. */
async function buscarSugerenciasCIE10(query: string, callback: (results: any[]) => void) {
  const q = query.trim();
  if (!q) { callback([]); return; }
  try {
    const { data } = await http.get('/api/externo/diagnosticos-cie10', { params: { buscar: q } });
    const resultados = (data.data as { codigo: string; descripcion: string }[])
      .map(c => ({ value: `${c.codigo} — ${c.descripcion}`, codigo: c.codigo, descripcion: c.descripcion }));
    callback(resultados);
  } catch {
    callback([]);
  }
}

function seleccionarSugerenciaCIE10(i: number, item: { codigo: string; descripcion: string }) {
  diagnosticos.value[i].codigo_cie10 = item.codigo;
  // El código queda dentro del mismo texto, al inicio — así se ve en la
  // misma barra sin necesitar una insignia aparte al lado.
  diagnosticos.value[i].descripcion = `${item.codigo} — ${item.descripcion}`;
}

/** Si se escribió el texto exacto de un diagnóstico del catálogo pero no se
 * le dio clic a la sugerencia (por ejemplo, tecleando Enter), el código
 * quedaría sin asociar — al salir del campo se busca una coincidencia
 * exacta (sin importar mayúsculas) y se completa igual, anteponiendo el
 * código al texto. */
async function verificarCodigoCIE10(i: number) {
  const dx = diagnosticos.value[i];
  if (dx.codigo_cie10 || !dx.descripcion.trim()) return;
  const texto = dx.descripcion.trim().toLowerCase();
  try {
    const { data } = await http.get('/api/externo/diagnosticos-cie10', { params: { buscar: dx.descripcion.trim() } });
    const match = (data.data as { codigo: string; descripcion: string }[]).find(c => c.descripcion.toLowerCase() === texto);
    if (match) {
      dx.codigo_cie10 = match.codigo;
      dx.descripcion = `${match.codigo} — ${match.descripcion}`;
    }
  } catch { /* deja el texto libre tal cual si la búsqueda falla */ }
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

function emptyForm() {
  return {
    primer_nombre: '', segundo_nombre: '', primer_apellido: '', segundo_apellido: '',
    genero: '', fecha_nacimiento: '', edad: null as number | null, tipo_documento: '', numero_documento: '',
    eps: '', municipio_capita: '', direccion_paciente: '', telefono_paciente: '', especialidad_requerida: '',
    tipo_referencia: '',
    servicio_ubicacion_actual: '', institucion_remitente: '',
    clinica_id: null as number | string | null,
    requiere_aislamiento: null as boolean | null, tipo_aislamiento: '', quien_remitente: '', telefono_contacto: '',
    urgencia_vital: null as boolean | null,
    gestante: null as boolean | null, condicion_especial: '',
    resumen_historia_clinica: '', observaciones: '',
  };
}

const form = ref(emptyForm());
const nombres = ref('');
const apellidos = ref('');

onMounted(() => {
  cargarMunicipios();
  cargarClinicas();
});

watch(() => form.value.fecha_nacimiento, (fecha) => {
  if (!fecha) {
    form.value.edad = null;
    return;
  }
  const nacimiento = new Date(`${fecha}T00:00:00`);
  const hoy = new Date();
  let edad = hoy.getFullYear() - nacimiento.getFullYear();
  const mes = hoy.getMonth() - nacimiento.getMonth();
  if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
  form.value.edad = Math.max(0, edad);
});

watch(nombres, (valor) => {
  const partes = valor.trim().split(/\s+/).filter(Boolean);
  form.value.primer_nombre = partes[0] ?? '';
  form.value.segundo_nombre = partes.slice(1).join(' ');
});

watch(apellidos, (valor) => {
  const partes = valor.trim().split(/\s+/).filter(Boolean);
  form.value.primer_apellido = partes[0] ?? '';
  form.value.segundo_apellido = partes.slice(1).join(' ');
});

watch(() => form.value.requiere_aislamiento, (v) => {
  if (v !== true) form.value.tipo_aislamiento = '';
});

function capitalizar(campo: 'primer_nombre' | 'segundo_nombre' | 'primer_apellido' | 'segundo_apellido' | 'resumen_historia_clinica' | 'condicion_especial' | 'quien_remitente' | 'institucion_remitente' | 'observaciones') {
  const val = form.value[campo];
  if (val && val.length === 1) {
    form.value[campo] = val.charAt(0).toUpperCase();
  } else if (val && val.length > 1) {
    form.value[campo] = val.charAt(0).toUpperCase() + val.slice(1);
  }
}

function capitalizarTexto(refTexto: Ref<string>) {
  const val = refTexto.value;
  if (val) refTexto.value = val.charAt(0).toUpperCase() + val.slice(1);
}

/** Igual que la restricción `isNumber` del campo real en Gomedisys — solo deja dígitos. */
function soloNumeros(campo: 'telefono_paciente') {
  form.value[campo] = form.value[campo].replace(/\D/g, '');
}

const nombreCompleto = computed(() =>
  [form.value.primer_nombre, form.value.segundo_nombre, form.value.primer_apellido, form.value.segundo_apellido]
    .filter(Boolean)
    .join(' ') || '—'
);

const progresoFormulario = computed(() => {
  const campos = [
    'tipo_documento', 'numero_documento', 'primer_nombre', 'primer_apellido',
    'genero', 'fecha_nacimiento', 'edad', 'municipio_capita', 'eps',
    'institucion_remitente', 'especialidad_requerida', 'urgencia_vital',
    'servicio_ubicacion_actual', 'resumen_historia_clinica',
  ];
  const llenos = campos.filter(c => {
    const v = form.value[c as keyof typeof form.value];
    return v !== null && v !== '' && v !== undefined;
  }).length;
  const dxCount = diagnosticos.value.filter(d => d.descripcion.trim()).length;
  const dxBonus = dxCount > 0 ? 1 : 0;
  return Math.min(100, Math.round(((llenos + dxBonus) / (campos.length + 1)) * 100));
});

const camposValidos = computed(() => {
  const required = ['tipo_documento', 'numero_documento', 'primer_nombre', 'primer_apellido',
    'genero', 'fecha_nacimiento', 'edad', 'municipio_capita', 'eps',
    'especialidad_requerida', 'tipo_referencia', 'servicio_ubicacion_actual', 'resumen_historia_clinica',
    'institucion_remitente', 'urgencia_vital', 'requiere_aislamiento', 'gestante', 'condicion_especial', 'direccion_paciente', 'telefono_paciente'];
  const valid: Record<string, boolean> = {};
  for (const c of required) {
    const v = form.value[c as keyof typeof form.value];
    valid[c] = v !== null && v !== '' && v !== undefined;
  }
  valid['tipo_aislamiento'] = form.value.requiere_aislamiento !== true || !!form.value.tipo_aislamiento;
  return valid;
});

const conteoDx = computed(() => diagnosticos.value.filter(d => d.descripcion.trim()).length);

/** Diagnóstico principal (el primero) para el modal de confirmación, con conteo de adicionales. */
const diagnosticoPrincipalTexto = computed(() => {
  const validos = diagnosticos.value.filter(d => d.descripcion.trim());
  if (!validos.length) return '—';
  // El código ya viene incluido dentro de d.descripcion (ver
  // seleccionarSugerenciaCIE10/verificarCodigoCIE10) — no se antepone otra vez.
  const primero = validos[0].descripcion;
  return validos.length > 1 ? `${primero} (y ${validos.length - 1} más)` : primero;
});

const diagnosticosValidos = computed(() =>
  diagnosticos.value.filter(d => d.descripcion.trim() || d.codigo_cie10.trim())
);

function dxTexto(dx: DiagnosticoItem): string {
  const desc = dx.descripcion.trim();
  const cod = dx.codigo_cie10.trim();
  return cod && desc.startsWith(cod) ? desc.slice(cod.length).replace(/^\s*[—–-]\s*/, '') : desc || cod || '—';
}

const fechaSolicitudTexto = computed(() => {
  if (!fechaSolicitud.value) return '—';
  const fecha = fechaSolicitud.value.toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' });
  const hora = fechaSolicitud.value.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
  return `${fecha} - ${hora}`;
});

const resumenPacienteCompleto = computed(() =>
  ['tipo_documento', 'numero_documento', 'primer_nombre', 'primer_apellido', 'genero', 'fecha_nacimiento', 'edad', 'municipio_capita', 'eps']
    .every(c => camposValidos.value[c]),
);

const resumenRemisionCompleto = computed(() =>
  camposValidos.value.institucion_remitente
  && camposValidos.value.especialidad_requerida
  && camposValidos.value.tipo_referencia
  && camposValidos.value.servicio_ubicacion_actual
  && camposValidos.value.urgencia_vital
  && camposValidos.value.resumen_historia_clinica
  && diagnosticos.value.some(d => d.descripcion.trim()),
);

const rules = {
  primer_nombre: [{ required: true, message: 'El primer nombre es requerido', trigger: 'blur' }],
  primer_apellido: [{ required: true, message: 'El primer apellido es requerido', trigger: 'blur' }],
  genero: [{ required: true, message: 'Seleccione el género del paciente', trigger: 'change' }],
  fecha_nacimiento: [{ required: true, message: 'Seleccione la fecha de nacimiento', trigger: 'change' }],
  edad: [{ required: true, message: 'La edad es requerida', trigger: 'blur' }],
  tipo_documento: [{ required: true, message: 'Seleccione el tipo de documento', trigger: 'change' }],
  numero_documento: [{ required: true, message: 'El número de documento es requerido', trigger: 'blur' }],
  eps: [{ required: true, message: 'Seleccione la EPS / aseguradora', trigger: 'change' }],
  municipio_capita: [{ required: true, message: 'Seleccione el municipio', trigger: 'change' }],
  institucion_remitente: [{ required: true, message: 'Seleccione la institución que refiere', trigger: 'change' }],

  especialidad_requerida: [{ required: true, message: 'Seleccione la especialidad requerida', trigger: 'change' }],
  tipo_referencia: [{ required: true, message: 'Seleccione el tipo de referencia', trigger: 'change' }],
  servicio_ubicacion_actual: [{ required: true, message: 'Seleccione el servicio / ubicación actual', trigger: 'change' }],
  urgencia_vital: [{ required: true, type: 'boolean', message: 'Indique si es una urgencia vital', trigger: 'change' }],
  resumen_historia_clinica: [{ required: true, message: 'La historia clínica es requerida', trigger: 'blur' }],
  quien_remitente: [{ required: true, message: 'Indique quién remite al paciente', trigger: 'blur' }],
  telefono_contacto: [{ required: true, message: 'El teléfono de contacto es obligatorio', trigger: 'blur' }],
};

/** Etiquetas legibles para mostrar en mensajes de error específicos. */
const CAMPO_LABELS: Record<string, string> = {
  primer_nombre: 'Primer nombre',
  primer_apellido: 'Primer apellido',
  genero: 'Género',
  fecha_nacimiento: 'Fecha de nacimiento',
  edad: 'Edad',
  tipo_documento: 'Tipo de documento',
  numero_documento: 'Número de documento',
  eps: 'EPS / Aseguradora',
  municipio_capita: 'Municipio',
  especialidad_requerida: 'Especialidad requerida',
  tipo_referencia: 'Tipo de referencia',
  institucion_remitente: 'Institución que refiere (IPS o EPS)',
  servicio_ubicacion_actual: 'Servicio / Ubicación actual',
  urgencia_vital: 'Urgencia vital',
  resumen_historia_clinica: 'Historia clínica',
  quien_remitente: 'Quien remite',
  telefono_contacto: 'Teléfono de contacto',
};

const MAX_ARCHIVO_MB = 10;
const MAX_ARCHIVOS = 10;

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
  const restantes = MAX_ARCHIVOS - adjuntos.value.length;
  if (restantes <= 0) {
    notify.warning(`Máximo ${MAX_ARCHIVOS} archivos permitidos. Elimine alguno para adjuntar otro.`);
    return;
  }

  const maxBytes = MAX_ARCHIVO_MB * 1024 * 1024;
  const demasiadoPesados = files.filter(f => f.size > maxBytes);
  const validos = files.filter(f => f.size <= maxBytes);

  if (demasiadoPesados.length) {
    notify.error(
      `${demasiadoPesados.length === 1 ? 'Este archivo supera' : 'Estos archivos superan'} el máximo de ${MAX_ARCHIVO_MB}MB: `
      + demasiadoPesados.map(f => `${f.name} (${(f.size / 1024 / 1024).toFixed(1)}MB)`).join(', '),
    );
  }

  const nuevos = validos.slice(0, restantes);
  if (validos.length > restantes) {
    notify.warning(`Solo se agregaron ${restantes} de ${validos.length} archivos válidos (límite ${MAX_ARCHIVOS} en total)`);
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

const IMAGENES_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'];

function esImagen(file: File): boolean {
  const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
  return IMAGENES_EXT.includes(ext);
}

const previewUrlCache = new WeakMap<File, string>();

/** Genera (y reutiliza) una URL local para previsualizar una imagen adjunta sin subirla todavía. */
function previewUrl(file: File): string {
  let url = previewUrlCache.get(file);
  if (!url) {
    url = URL.createObjectURL(file);
    previewUrlCache.set(file, url);
  }
  return url;
}

const lightboxSrc = ref<string | null>(null);
const lightboxName = ref('');

function abrirLightbox(file: File): void {
  if (!esImagen(file)) return;
  lightboxSrc.value = previewUrl(file);
  lightboxName.value = file.name;
}

/** Abre PDF/Word/Excel/otros en una pestaña nueva usando el archivo local
 * (aún no subido). El navegador renderiza los PDF de forma nativa; el resto
 * se abre o descarga con la app asociada del sistema — es el máximo de
 * "previsualización" posible antes de enviar el formulario. */
function abrirArchivo(file: File): void {
  window.open(previewUrl(file), '_blank', 'noopener');
}

function cerrarLightbox(): void {
  lightboxSrc.value = null;
  lightboxName.value = '';
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

async function abrirConfirmacion(): Promise<void> {
  const validDx = diagnosticos.value.filter(d => d.descripcion.trim());
  if (validDx.length === 0) {
    notify.error('Agregue al menos un diagnóstico: busque por código (ej: J18) o escriba directamente el síntoma o nombre del diagnóstico.');
    return;
  }

  formRef.value?.validate((valid: boolean, invalidFields?: Record<string, Array<{ message?: string }>>) => {
    if (valid) {
      fechaSolicitud.value = new Date();
      showConfirmResumen.value = true;
      return;
    }
    const nombres = Object.keys(invalidFields ?? {}).map(campo => CAMPO_LABELS[campo] ?? campo);
    notify.error(
      nombres.length
        ? `Faltan campos por completar: ${nombres.join(', ')}.`
        : 'Por favor complete todos los campos requeridos.',
    );
  });
}

const modalExito = ref(false);
const ultimoEnviado = ref({
  paciente: '', documento: '', especialidad: '', diagnosticos: '', servicio: '', adjuntos: '',
});

function onCerrarExito() {
  ultimoEnviado.value = { paciente: '', documento: '', especialidad: '', diagnosticos: '', servicio: '', adjuntos: '' };
}

async function guardar() {
  const invalidFields = await new Promise<Record<string, Array<{ message?: string }>> | undefined>((resolve) => {
    formRef.value?.validate((valid: boolean, fields?: Record<string, Array<{ message?: string }>>) => resolve(valid ? undefined : fields));
  });
  if (invalidFields) {
    const nombres = Object.keys(invalidFields).map(campo => CAMPO_LABELS[campo] ?? campo);
    notify.error(nombres.length ? `Faltan campos por completar: ${nombres.join(', ')}.` : 'Por favor complete todos los campos requeridos.');
    return;
  }
  guardando.value = true;
  try {
    const payload = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
      if (value !== null && value !== '') {
        payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
      }
    });
    const validDx = diagnosticos.value.filter(d => d.descripcion.trim());
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
      // El código ya puede venir dentro de d.descripcion (diagnósticos elegidos
      // del catálogo) — solo se antepone si todavía no está ahí.
      diagnosticos: validDx.map(d => (d.codigo_cie10 && !d.descripcion.startsWith(d.codigo_cie10)) ? `${d.codigo_cie10} — ${d.descripcion}` : d.descripcion).join('\n') || '—',
      servicio: form.value.servicio_ubicacion_actual || '—',
      adjuntos: adjuntos.value.length ? `${adjuntos.value.length} archivo(s)` : '',
    };
    showConfirmResumen.value = false;
    modalExito.value = true;
    form.value = emptyForm();
    nombres.value = '';
    apellidos.value = '';
    adjuntos.value = [];
    diagnosticos.value = [{ codigo_cie10: '', descripcion: '' }];
  } catch (e: any) {
    const errors = e.response?.data?.data?.errors || e.response?.data?.errors;
    if (errors) {
      notify.error(Object.values(errors).flat().join('\n'));
    } else if (e.response?.status === 503) {
      notify.error(e.response?.data?.message || 'No se pudo enviar el correo y la solicitud no quedó registrada. Intente nuevamente.');
    } else if (e.response?.status === 413) {
      notify.error('Los archivos adjuntos superan el tamaño máximo permitido por el servidor. Reduzca el tamaño o la cantidad e intente de nuevo.');
    } else if (e.response?.status >= 500) {
      notify.error('El servidor tuvo un problema al procesar la solicitud. Intente de nuevo en unos minutos.');
    } else if (!e.response) {
      notify.error('No se pudo conectar con el servidor. Verifique su conexión a internet e intente de nuevo.');
    } else {
      notify.error(e.response?.data?.message || 'Error al enviar la solicitud');
    }
  } finally { guardando.value = false; }
}

function limpiarFormulario() {
  ultimaClinicaValida = null;
  form.value = emptyForm();
  nombres.value = '';
  apellidos.value = '';
  adjuntos.value = [];
  diagnosticos.value = [{ codigo_cie10: '', descripcion: '' }];
  formRef.value?.clearValidate();
}
</script>

<style scoped>
/* ── Page ── */
.solicitud-page {
  padding: .5rem 1.2rem;
  gap: .45rem;
  background: linear-gradient(160deg, #F0F5FF 0%, #E0EAF7 40%, #F0F5FF 100%);
}

/* ── Stepper compacto (header navy) ── */
.rf-stepper {
  display: flex;
  align-items: center;
  gap: .6rem;
  flex-shrink: 0;
  background: linear-gradient(90deg, #16468e 0%, #1e5bbf 100%);
  border: 1px solid #123a78;
  border-radius: 14px;
  padding: .4rem .8rem;
  box-shadow: 0 6px 20px rgba(13, 45, 107, .28), inset 0 1px 0 rgba(255,255,255,.12);
}
.rf-stepper-back {
  display: grid;
  place-items: center;
  width: 30px; height: 30px;
  border-radius: 9px;
  border: 1px solid rgba(255,255,255,.22);
  background: rgba(255,255,255,.12);
  color: #fff;
  cursor: pointer;
  transition: all .2s ease;
  flex-shrink: 0;
}
.rf-stepper-back:hover {
  background: rgba(255,255,255,.22);
}
.rf-stepper-title {
  flex: 1;
  min-width: 0;
}
.rf-stepper-title h2 {
  font-size: 1.05rem;
  font-weight: 800;
  color: #fff;
  margin: 0;
  line-height: 1.25;
}
.rf-stepper-title p {
  font-size: .74rem;
  font-weight: 500;
  color: rgba(255,255,255,.65);
  margin: 2px 0 0;
}
.rf-stepper-progress {
  display: flex; align-items: center; gap: 8px;
  flex-shrink: 0;
  position: relative;
}
.rf-stepper-progress-track {
  width: 80px; height: 6px;
  border-radius: 4px;
  background: rgba(255,255,255,.22);
  overflow: hidden;
}
.rf-stepper-progress-bar {
  height: 100%;
  border-radius: 4px;
  background: linear-gradient(90deg, #7dd3fc, #38bdf8);
  transition: width .4s ease;
}
.rf-stepper-progress-label {
  font-size: .72rem;
  font-weight: 700;
  color: #fff;
  min-width: 32px;
  text-align: right;
}

/* Pasos en vivo dentro del header navy */
.rf-stepper-steps {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.rf-step-pill {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px 4px 5px;
  border-radius: 999px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.14);
  font-size: 10.5px;
  font-weight: 700;
  color: rgba(255,255,255,.75);
  white-space: nowrap;
  transition: all .25s ease;
}
.rf-step-num {
  display: grid;
  place-items: center;
  width: 18px; height: 18px;
  border-radius: 50%;
  background: rgba(255,255,255,.16);
  color: #fff;
  font-size: 9px;
  font-weight: 800;
}
.rf-step-pill.done {
  background: #fff;
  border-color: #fff;
  color: #16468e;
  box-shadow: 0 3px 10px rgba(0,0,0,.18);
}
.rf-step-pill.done .rf-step-num {
  background: #22c55e;
  color: #fff;
}
.rf-step-line {
  width: 14px; height: 1px;
  background: rgba(255,255,255,.25);
  flex-shrink: 0;
}
@media (max-width: 1280px) {
  .rf-stepper-steps { display: none; }
}

/* ── Layout: bento ── */
.rf-body {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  display: flex;
  gap: .6rem;
}
.rf-form-col {
  flex: 1;
  min-width: 0;
  height: 100%;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 4px;
  display: flex;
  flex-direction: column;
  gap: .55rem;
}

/* ── Layout bento ── */
.rf-bento-top {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .55rem;
  align-items: stretch;
}
.rf-bento-top > .rf-section { min-width: 0; display: flex; flex-direction: column; }
.rf-bento-top .rf-subsection { flex: 1; display: flex; flex-direction: column; }
.rf-bento-top .rf-grid { grid-template-columns: repeat(4, 1fr); }
.rf-grid-trio { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 .8rem; }
.rf-section--compact { padding: .6rem .8rem .7rem; }
.rf-section--compact .rf-section-header { margin-bottom: .45rem; padding-bottom: .35rem; }
.rf-section--compact .rf-section-icon { width: 26px; height: 26px; border-radius: 8px; }
.rf-section--compact .rf-section-heading h3 { font-size: .8rem; }
.rf-section--compact .rf-section-heading p { font-size: .62rem; }
@media (max-width: 1150px) {
  .rf-bento-top { grid-template-columns: 1fr; }
}

/* ── Form overrides ── */
:deep(.rf-form-col .el-form) { display: flex; flex-direction: column; gap: .75rem; }
:deep(.rf-form-col .el-form-item) { margin-bottom: 0; display: flex; flex-direction: column; }
:deep(.rf-form-col .el-form-item__label) {
  color: #475569; font-size: .7rem; font-weight: 600;
  padding-bottom: .12rem; line-height: 1.2;
  min-height: calc(1.2em + .15rem);
  position: relative;
  display: block;
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding-right: 22px;
}
:deep(.rf-form-col .el-form-item__error) { display: none; }
:deep(.rf-form-col .el-input__wrapper),
:deep(.rf-form-col .el-select__wrapper),
:deep(.rf-form-col .el-textarea__inner) {
  box-shadow: 0 0 0 1px #dbe3ef inset;
  border-radius: 9px;
  background: #fff;
  transition: box-shadow .2s ease;
}
:deep(.rf-form-col .el-input__wrapper.is-focus),
:deep(.rf-form-col .el-select__wrapper.is-focus) {
  box-shadow: 0 0 0 1.5px #2563eb inset, 0 0 0 4px rgba(37,99,235,.12);
}
:deep(.rf-form-col .el-input__wrapper:hover),
:deep(.rf-form-col .el-select__wrapper:hover) { box-shadow: 0 0 0 1px #93c5fd inset; }
:deep(.rf-field.is-success .el-input__wrapper),
:deep(.rf-field.is-success .el-select__wrapper),
:deep(.rf-field.is-success .el-textarea__inner) {
  box-shadow: 0 0 0 1px #bbf7d0 inset;
}
:deep(.rf-field.is-success .el-input__wrapper.is-focus),
:deep(.rf-field.is-success .el-select__wrapper.is-focus) {
  box-shadow: 0 0 0 1.5px #2563eb inset, 0 0 0 4px rgba(37,99,235,.12);
}
:deep(.rf-form-col .el-input__wrapper),
:deep(.rf-form-col .el-select__wrapper) { min-height: 31px; }
:deep(.rf-dx-block .el-input__wrapper),
:deep(.rf-dx-block .el-select__wrapper) { min-height: 30px; }
:deep(.rf-form-col .el-input-number) { width: 100%; }
:deep(.rf-form-col .el-input-number .el-input__wrapper) { min-height: 31px; }
:deep(.rf-form-col .el-textarea__inner) { min-height: 31px !important; border-radius: 9px; }
:deep(.rf-form-col .el-input__inner),
:deep(.rf-form-col .el-select__selected-item) { font-size: .78rem; }

/* ── Sections ── */
.rf-section {
  position: relative;
  background: linear-gradient(180deg, #FAFBFF 0%, #F5F8FE 100%);
  border: 1px solid #DBE4F0;
  border-radius: 16px;
  padding: .8rem 1rem .9rem;
  box-shadow: 0 8px 28px rgba(13, 45, 107, .09);
  overflow: hidden;
}
.rf-section-accent {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
}
.rf-section-header {
  display: flex;
  align-items: center;
  gap: .55rem;
  margin-bottom: .55rem;
  padding-bottom: .45rem;
  border-bottom: 1px solid #EEF2F9;
}
.rf-section-icon {
  position: relative;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 9px;
  flex-shrink: 0;
}
.rf-section-num {
  position: absolute;
  top: -5px; right: -5px;
  display: grid;
  place-items: center;
  width: 16px; height: 16px;
  border-radius: 50%;
  background: #2563eb;
  color: #fff;
  font-size: 9px;
  font-weight: 800;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(37,99,235,.35);
}
.rf-section-heading h3 {
  font-size: .9rem;
  font-weight: 800;
  color: #0D2D6B;
  margin: 0;
}
.rf-section-heading p {
  font-size: .68rem;
  font-weight: 500;
  color: #8A9AB5;
  margin: 1px 0 0;
}

/* ── Grid ── */
.rf-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: .55rem .8rem;
}
.rf-col-1 { grid-column: span 1; }
.rf-col-full { grid-column: 1 / -1; }
.rf-col-2 { grid-column: span 2; }
.rf-col-3 { grid-column: span 5; }
.rf-col-half { grid-column: span 2; }
.rf-col-third { grid-column: span 2; }
.rf-col-quarter { grid-column: span 1; }
.rf-col-fifth { grid-column: span 1; }
.rf-col-sixth { grid-column: span 1; }
.rf-col-4 { grid-column: span 4; }
.rf-col-3-fix { grid-column: span 3; }
.rf-col-5 { grid-column: span 5; }
.rf-mt { margin-top: .7rem; }

/* ── Input prefix icons ── */
.rf-input-icon { color: #94A3B8; margin-right: 2px; }
:deep(.rf-form-col .el-input__prefix) { color: #94A3B8; }

/* ── Subsection ── */
.rf-subsection {
  margin-top: .4rem;
  padding-top: .4rem;
  border-top: 1px dashed #E2E8F0;
}
.rf-subsection:first-of-type {
  margin-top: 0;
  padding-top: 0;
  border-top: none;
}
.rf-subsection-row {
  display: flex;
  gap: .5rem;
  align-items: stretch;
}
.rf-subsection-row.rf-subsection-first {
  margin-top: 0;
  padding-top: 0;
  border-top: none;
}
.rf-subsection-row .rf-subsection-half {
  flex: 1;
  min-width: 0;
  margin-top: 0;
  padding-top: 0;
  border-top: none;
}
.rf-subsection-row .rf-dx-block {
  height: 100%;
}
.rf-subsection-header {
  display: flex;
  align-items: center;
  gap: .4rem;
  margin-bottom: .35rem;
}
.rf-subsection-icon {
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  flex-shrink: 0;
}
.rf-sub-icon-green { background: #DCFCE7; color: #15803D; }
.rf-sub-icon-blue { background: #DBEAFE; color: #2563EB; }
.rf-sub-icon-amber { background: #FEF3C7; color: #D97706; }
.rf-sub-icon-purple { background: #F3E8FF; color: #7C3AED; }
.rf-sub-icon-cyan { background: #CFFAFE; color: #0891B2; }
.rf-subsection-header h4 {
  font-size: .76rem;
  font-weight: 800;
  color: #0D2D6B;
  margin: 0;
}
.rf-subsection-note {
  font-size: .62rem;
  font-weight: 500;
  color: #94A3B8;
  margin-left: .4rem;
}
.dark .rf-subsection-note { color: #64748b; }
.rf-subsection-badge {
  margin-left: auto;
  font-size: .58rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: #FEF2F2;
  color: #DC2626;
  border: 1px solid #FECACA;
}
.rf-badge-blue {
  background: #EFF6FF;
  color: #2563EB;
  border-color: #BFDBFE;
}

/* ── Diagnósticos ── */
.rf-dx-block {
  background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
  border: 1px solid #BFDBFE;
  border-radius: 10px;
  padding: .4rem .5rem;
}
.dark .rf-dx-block { background: linear-gradient(135deg, #1e293b, #172033); border-color: #334155; }
.dark .rf-dx-title { color: #93c5fd; }
.dark .rf-dx-add { border-color: #3b82f6 !important; background: #1e3a5f !important; color: #93c5fd !important; }
.dark .rf-dx-label { color: #94a3b8; }
.dark .rf-dx-suggestion-code { color: #93c5fd; }
.dark .rf-dx-suggestion-desc { color: #94a3b8; }
.dark .rf-dx-empty { color: #64748b; }
.rf-dx-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .3rem;
}
.rf-dx-title {
  font-size: .72rem;
  font-weight: 800;
  color: #1E40AF;
}
.rf-req { color: #DC2626; }
.rf-dx-add {
  border-color: #0D2D6B !important;
  background: #DBEAFE !important;
  color: #0D2D6B !important;
  font-size: .66rem;
  font-weight: 600;
  border-radius: 7px;
  height: 28px !important;
}
.rf-dx-row {
  display: flex;
  align-items: center;
  gap: .35rem;
  margin-bottom: .3rem;
}
.rf-dx-code { width: 110px; flex-shrink: 0; }
.rf-dx-desc { flex: 1; }
.rf-dx-remove {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 6px;
  border: 1px solid #FCA5A5;
  background: #FEF2F2;
  color: #DC2626;
  cursor: pointer;
  flex-shrink: 0;
  transition: all .2s ease;
}
.rf-dx-remove:hover { background: #DC2626; color: #fff; }
.rf-dx-empty { font-size: .72rem; color: #94A3B8; margin: .2rem 0 0; }

/* ── Diagnósticos: lista vertical ── */
.rf-dx-inline-row { display: flex; align-items: center; gap: .3rem; width: 100%; }
.rf-dx-inline-row .el-input { flex: 1; }
.rf-dx-section { margin-top: 0; }
/* La lista hace scroll interno — agregar diagnósticos no agranda la tarjeta */
.rf-dx-list {
  overflow-y: auto;
  padding-right: .35rem;
}
.rf-dx-list::-webkit-scrollbar { width: 5px; }
.rf-dx-list::-webkit-scrollbar-track { background: transparent; }
.rf-dx-list::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
.dark .rf-dx-list::-webkit-scrollbar-thumb { background: #334155; }
.rf-dx-preview {
  font-size: .64rem;
  color: #475569;
  margin: .35rem 0 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rf-dx-preview strong { color: #0D2D6B; }
.dark .rf-dx-preview { color: #94a3b8; }
.dark .rf-dx-preview strong { color: #bfdbfe; }

/* ── Modal de diagnósticos ── */
:deep(.dx-dialog) { border-radius: 16px; overflow: hidden; }
:deep(.dx-dialog .el-dialog__header) { display: none; }
:deep(.dx-dialog .el-dialog__body) { padding: 0; }
.dx-body { position: relative; padding: 1rem 1.1rem; background: #fff; }
.dx-body::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, #16468e 0%, #1e5bbf 60%, #3b82f6 100%);
}
.dark .dx-body { background: #161b28; }
.dx-head {
  display: flex; align-items: center; gap: .55rem;
  margin-bottom: .7rem;
  padding: .5rem .65rem;
  border-radius: 10px;
  background: linear-gradient(90deg, #eff6ff 0%, #dbeafe 100%);
  border: 1px solid #bfdbfe;
}
.dark .dx-head {
  background: linear-gradient(90deg, rgba(30,91,191,.18), rgba(59,130,246,.12));
  border-color: #2b3550;
}
.dx-head-icon {
  width: 30px; height: 30px; border-radius: 9px;
  display: grid; place-items: center;
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  color: #16468e;
}
.dx-head h3 { margin: 0; font-size: .9rem; font-weight: 800; color: #0D2D6B; }
.dx-head p { margin: .05rem 0 0; font-size: .66rem; color: #8A9AB5; }
.dark .dx-head h3 { color: #bfdbfe; }
.dark .dx-head p { color: #64748b; }
.dx-modal-list { max-height: 340px; }
.dx-head-icon--violet {
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  color: #16468e;
}
.dark .dx-head-icon--violet {
  background: linear-gradient(135deg, rgba(30,91,191,.3), rgba(59,130,246,.2));
  color: #93c5fd;
}
.dx-foot { display: flex; justify-content: flex-end; margin-top: .7rem; }

/* Botón "Ver más" pequeño dentro del textarea de HC, a la altura de la última línea */
.rf-hc-field { position: relative; width: 100%; }
.rf-hc-ver-mas {
  position: absolute;
  right: 13px;
  bottom: 3px;
  z-index: 2;
  border: 0;
  background: rgba(255,255,255,.9);
  padding: 0 1px;
  font-size: .58rem;
  font-weight: 600;
  line-height: 1.1;
  color: #16468e;
  cursor: pointer;
  transition: color .15s ease;
  border-radius: 2px;
}
.rf-hc-ver-mas:hover { color: #123a78; text-decoration: underline; }
.dark .rf-hc-ver-mas { background: rgba(29,35,51,.9); color: #93c5fd; }
.dark .rf-hc-ver-mas:hover { color: #bfdbfe; }
:deep(.hc-modal-textarea .el-textarea__inner) { resize: vertical; min-height: 180px !important; }
.rf-dx-label {
  display: block;
  color: #475569; font-size: .74rem; font-weight: 600;
  padding-bottom: .3rem;
}
.rf-dx-row-block {
  margin-bottom: .35rem;
  padding: .45rem .55rem .55rem;
  background: #f5f8ff;
  border: 1px solid #dbeafe;
  border-radius: 9px;
}
.dark .rf-dx-row-block { background: #1a2236; border-color: #26314a; }
.rf-dx-row-block:last-of-type { margin-bottom: .35rem; }
.rf-dx-row-tag {
  display: inline-flex;
  align-items: center;
  padding: 1px 9px;
  border-radius: 999px;
  background: linear-gradient(135deg, #16468e, #1e5bbf);
  border: 1px solid #16468e;
  font-size: .6rem;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #fff;
  margin: 0 0 .3rem;
}
.rf-dx-row-tag--sec {
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  border-color: #93c5fd;
  color: #16468e;
}
.dark .rf-dx-row-tag { background: rgba(30,91,191,.3); border-color: rgba(59,130,246,.4); color: #bfdbfe; }
.dark .rf-dx-row-tag--sec { background: rgba(59,130,246,.14); border-color: rgba(59,130,246,.3); color: #93c5fd; }
.rf-dx-suggestion-code { font-weight: 700; color: #0D2D6B; margin-right: .4rem; }
.rf-dx-suggestion-desc { color: #475569; }

/* ── Botón Adjuntar archivos ── */
.rf-attach-btn {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  width: 100%; height: 30px;
  border: 1px dashed #93C5FD; border-radius: 8px;
  background: #EFF6FF; color: #16468E;
  font-size: 13px; font-weight: 500; cursor: pointer;
  transition: all .2s ease;
}
.rf-attach-btn:hover { background: #DBEAFE; border-color: #16468E; }
.rf-add-dx-btn {
  width: auto; padding: 0 16px;
  border-style: solid;
  border-color: #93c5fd;
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  color: #16468e;
  font-weight: 600;
}
.rf-add-dx-btn:hover { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-color: #1e5bbf; color: #123a78; }
.dark .rf-add-dx-btn { background: rgba(30,91,191,.18); border-color: rgba(59,130,246,.35); color: #93c5fd; }
.dark .rf-add-dx-btn:hover { background: rgba(30,91,191,.3); border-color: #3b82f6; color: #bfdbfe; }
.dark .rf-attach-btn { background: #1e293b; border-color: #334155; color: #93c5fd; }
.dark .rf-attach-btn:hover { background: #253449; border-color: #3b82f6; }

/* ── Botones Sí/No gestante ── */
.rf-gestante-btns { display: flex; gap: 6px; width: 100%; }
.rf-gestante-btn {
  flex: 1;
  height: 31px;
  border: 1px solid #d4deea;
  border-radius: 9px;
  background: #fff;
  color: #64748B;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all .2s ease;
}
.rf-gestante-btn:hover { border-color: #2563eb; color: #2563eb; }
.rf-gestante-btn.active {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  border-color: #2563eb;
  color: #fff;
  box-shadow: 0 4px 12px rgba(37,99,235,.3);
}
.dark .rf-gestante-btn { background: #1e293b; border-color: #334155; color: #94a3b8; }
.dark .rf-gestante-btn:hover { border-color: #60a5fa; color: #93c5fd; }
.dark .rf-gestante-btn.active { background: #2563eb; border-color: #2563eb; color: #fff; }
.rf-urgencia-vital-label {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  color: #b91c1c;
}
.rf-urgencia-vital-label > span {
  display: inline-grid;
  place-items: center;
  width: 13px;
  height: 13px;
  border-radius: 50%;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 9px;
  font-weight: 900;
}
.rf-urgencia-vital-field--active :deep(.el-form-item__label) { color: #b91c1c; }
.rf-urgencia-vital-btns .rf-urgencia-vital-yes-active,
.rf-urgencia-vital-btns .rf-urgencia-vital-yes-active:hover {
  background: linear-gradient(135deg, #ef4444, #b91c1c);
  border-color: #dc2626;
  color: #fff;
  box-shadow: 0 3px 9px rgba(220, 38, 38, .25);
}
.dark .rf-urgencia-vital-btns .rf-urgencia-vital-yes-active,
.dark .rf-urgencia-vital-btns .rf-urgencia-vital-yes-active:hover {
  background: #b91c1c;
  border-color: #ef4444;
}

/* ── Adjuntos ── */
/* ── Soportes: dropzone que llena el espacio restante de la tarjeta ── */
.rf-attach-compact { flex: 1; display: flex; flex-direction: column; gap: .45rem; min-height: 0; margin-top: .5rem; }
.rf-attach-drop {
  position: relative;
  flex: 1;
  min-height: 110px;
  border: 1.5px dashed #93C5FD;
  border-radius: 10px;
  background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .2rem;
  padding: .6rem;
  cursor: pointer;
  color: #2563EB;
  transition: all .2s ease;
}
.rf-attach-drop:hover { border-color: #2563EB; background: rgba(219, 234, 254, .55); }
.rf-attach-drop p { font-size: .7rem; font-weight: 700; color: #1E40AF; margin: 0; }
.rf-attach-drop small { font-size: .6rem; color: #3B82F6; }
.rf-attach-drop .rf-attach-count { position: absolute; top: .4rem; right: .5rem; margin-left: 0; }
.dark .rf-attach-drop { background: #1e293b; border-color: #334155; color: #93c5fd; }
.dark .rf-attach-drop:hover { background: #253449; border-color: #3b82f6; }
.dark .rf-attach-drop p { color: #bfdbfe; }
.dark .rf-attach-drop small { color: #60a5fa; }
.rf-attach-active .rf-attach-drop {
  border-color: #3B82F6;
  animation: rf-drop-pulse 1s ease-in-out infinite;
}
.rf-attach-count {
  margin-left: auto;
  background: #0D2D6B;
  color: #fff;
  padding: 1px 8px;
  border-radius: 10px;
  font-size: .62rem;
}
.ft-pdf { background: #FEE2E2; color: #DC2626; }
.ft-img { background: #DBEAFE; color: #2563EB; }
.ft-doc { background: #DBEAFE; color: #1E40AF; }
.ft-xls { background: #DCFCE7; color: #15803D; }
.ft-ppt { background: #FED7AA; color: #C2410C; }
.ft-zip { background: #F3E8FF; color: #7C3AED; }
.ft-default { background: #F1F5F9; color: #475569; }

/* ── Vista previa pequeña de adjuntos ── */
.rf-file-mini-grid {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  margin-top: .5rem;
}
.rf-file-mini-item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .2rem;
  width: 56px;
}
.rf-file-mini-thumb {
  width: 48px; height: 48px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid #BFDBFE;
  background: #fff;
  cursor: zoom-in;
  transition: transform .15s ease;
}
.rf-file-mini-thumb:hover { transform: scale(1.08); }
.rf-file-mini-badge {
  width: 48px; height: 48px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 8px;
  font-size: .56rem; font-weight: 800;
}
.rf-file-mini-badge-click { cursor: pointer; transition: transform .15s ease; }
.rf-file-mini-badge-click:hover { transform: scale(1.08); }
.rf-file-mini-name {
  font-size: .52rem;
  font-weight: 600;
  color: #475569;
  max-width: 56px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: center;
}
.rf-file-mini-remove {
  position: absolute;
  top: -5px; right: -5px;
  display: grid;
  place-items: center;
  width: 16px; height: 16px;
  border-radius: 50%;
  background: #fff;
  color: #94A3B8;
  border: 1px solid #E2E8F0;
  cursor: pointer;
  padding: 0;
  transition: all .2s ease;
}
.rf-file-mini-remove:hover { color: #DC2626; border-color: #FCA5A5; background: #FEF2F2; }

/* ── Footer ── */
.rf-card-actions {
  display: flex;
  justify-content: flex-end;
  gap: .5rem;
  margin-top: .55rem;
  padding-top: .5rem;
  border-top: 1px dashed #E2E8F0;
}
.dark .rf-card-actions { border-top-color: #334155; }
.rf-btn-back {
  background: #fff !important;
  border-color: #CBD5E1 !important;
  color: #475569 !important;
  font-weight: 600;
  border-radius: 9px;
  height: 34px !important;
  padding: 0 18px !important;
}
.rf-btn-back:hover {
  color: #0D2D6B !important;
  border-color: #0D2D6B !important;
  background: #EFF6FF !important;
}
.rf-btn-next, .rf-btn-submit {
  background: linear-gradient(135deg, #0D2D6B, #16468E) !important;
  border: none !important;
  color: #fff !important;
  font-weight: 700;
  border-radius: 9px;
  height: 34px !important;
  padding: 0 22px !important;
  box-shadow: 0 3px 12px rgba(13, 45, 107, .2);
}
.rf-btn-next:hover, .rf-btn-submit:hover {
  background: linear-gradient(135deg, #16468E, #1E5BBF) !important;
  box-shadow: 0 6px 20px rgba(13, 45, 107, .35);
  transform: translateY(-1px);
}

/* ── Dialog confirmación ── */
:deep(.confirm-dialog:not(.clinica-dialog)) {
  width: min(580px, 94vw) !important;
  height: min(400px, calc(100dvh - 24px)) !important;
  max-height: calc(100dvh - 24px) !important;
  margin: 0 auto !important;
  box-sizing: border-box;
}
:deep(.confirm-dialog) {
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(13, 45, 107, .25);
  max-height: calc(100vh - 3rem);
  display: flex;
  flex-direction: column;
  --el-dialog-padding-primary: 0;
  padding: 0;
}
:deep(.confirm-dialog .el-dialog__header) { display: none; }
:deep(.confirm-dialog .el-dialog__body) { padding: 0; overflow: hidden; flex: 1; min-height: 0; display: flex; flex-direction: column; }
:deep(.confirm-dialog .el-dialog__footer) { padding: 0; flex-shrink: 0; }
.confirm-body {
  padding: 0;
  position: relative;
  background: #F8FAFC;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.confirm-close-btn {
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
.confirm-close-btn:hover { color: #DC2626; border-color: #FCA5A5; background: #FEF2F2; }

/* Header claro */
.confirm-head {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .38rem 3.7rem .38rem .65rem;
  background: #fff;
  border-bottom: 1px solid #EEF2F9;
  flex-shrink: 0;
}
.confirm-head::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(79,70,229,.06) 1.4px, transparent 1.4px);
  background-size: 15px 15px;
  -webkit-mask-image: linear-gradient(115deg, rgba(0,0,0,.9), transparent 70%);
  mask-image: linear-gradient(115deg, rgba(0,0,0,.9), transparent 70%);
  pointer-events: none;
}
.confirm-head-icon {
  position: relative;
  width: 29px; height: 29px;
  border-radius: 9px;
  background: linear-gradient(135deg, #4F46E5, #7C3AED);
  display: grid; place-items: center;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 6px 16px rgba(79, 70, 229, .3);
}
.confirm-head-info { position: relative; flex: 1; min-width: 0; }
.confirm-head-title { margin: 0; color: #0F172A; font-size: .82rem; font-weight: 800; }
.confirm-head-sub { margin: .1rem 0 0; color: #64748b; font-size: .58rem; }
.confirm-head-badge {
  position: relative;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .22rem .5rem;
  border-radius: 999px;
  background: #EEF2FF;
  color: #4F46E5;
  font-size: .52rem;
  font-weight: 700;
}
.confirm-head-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

/* ── Animaciones de entrada del resumen ── */
@keyframes confirmFadeUp {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.confirm-main > * { animation: confirmFadeUp .38s ease-out both; }
.confirm-main > *:nth-child(1) { animation-delay: .05s; }
.confirm-main > *:nth-child(2) { animation-delay: .12s; }
.confirm-main > *:nth-child(3) { animation-delay: .19s; }

/* ── Modal registro de clínica ── */
/* Encabezado del modal — las clases .confirm-hero* existían en el HTML sin
   estilos: por eso el ícono y el título quedaban pegados al borde superior. */
.confirm-hero {
  display: flex;
  align-items: center;
  gap: .65rem;
  margin: .9rem 1.1rem .2rem;
  padding: .6rem .75rem;
  border-radius: 12px;
  background: linear-gradient(90deg, #eff6ff 0%, #dbeafe 100%);
  border: 1px solid #bfdbfe;
}
.dark .confirm-hero {
  background: linear-gradient(90deg, rgba(30,91,191,.18), rgba(59,130,246,.12));
  border-color: #2b3550;
}
.confirm-icon-wrap {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, #dbeafe, #bfdbfe);
  color: #16468e;
}
.dark .confirm-icon-wrap {
  background: linear-gradient(135deg, rgba(30,91,191,.3), rgba(59,130,246,.2));
  color: #93c5fd;
}
.confirm-hero-text { flex: 1; min-width: 0; }
.confirm-title { margin: 0; font-size: .88rem; font-weight: 800; color: #0D2D6B; }
.dark .confirm-title { color: #bfdbfe; }
.confirm-subtitle { margin: .1rem 0 0; font-size: .68rem; color: #64748b; line-height: 1.45; }
.dark .confirm-subtitle { color: #94a3b8; }

.clinica-form {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .6rem .75rem;
  padding: .8rem 1.1rem .45rem;
}
.clinica-section {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: .45rem;
  margin: .35rem 0 0;
  font-size: .62rem;
  font-weight: 800;
  letter-spacing: .09em;
  text-transform: uppercase;
  color: #64748b;
}
.clinica-section:first-child { margin-top: 0; }
.clinica-section-bar {
  width: 3px; height: 12px;
  border-radius: 2px;
  flex-shrink: 0;
}
.clinica-bar-blue { background: linear-gradient(180deg, #3b82f6, #1d4ed8); }
.clinica-bar-green { background: linear-gradient(180deg, #34d399, #059669); }
.clinica-field { display: flex; flex-direction: column; gap: .26rem; min-width: 0; }
.clinica-field-full { grid-column: 1 / -1; }
.clinica-field > span {
  font-size: .68rem;
  font-weight: 700;
  color: #475569;
}
.clinica-field > span em { color: #dc2626; font-style: normal; }
.clinica-field :deep(.el-input__wrapper),
.clinica-field :deep(.el-select__wrapper) {
  border-radius: 10px;
  min-height: 38px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  box-shadow: none;
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.clinica-field :deep(.el-input__wrapper.is-focus),
.clinica-field :deep(.el-select__wrapper.is-focused) {
  background: #fff;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, .14);
}
.clinica-field :deep(.el-input__prefix) { color: #94a3b8; }
.clinica-field :deep(.el-input__wrapper.is-focus .el-input__prefix) { color: #3b82f6; }
.clinica-hint {
  display: flex;
  align-items: center;
  gap: .3rem;
  font-size: .62rem;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid #d1fae5;
  border-radius: 8px;
  padding: .3rem .5rem;
}

.confirm-main {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-template-rows: auto minmax(0, 1fr);
  gap: .3rem;
  padding: .32rem .48rem .35rem;
  min-width: 0;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

/* Keep every section on the same three-column grid as the clinical history. */
.confirm-c-paciente { grid-column: 1; grid-row: 1; }
.confirm-c-remision { grid-column: 2; grid-row: 1; }
.confirm-c-dx { grid-column: 3; grid-row: 1; }
.confirm-c-hc { grid-column: 1 / 3; grid-row: 2; }
.confirm-c-side { grid-column: 3; grid-row: 2; }

.confirm-c-dx .confirm-card-body {
  max-height: 150px;
  overflow-y: auto;
  scrollbar-width: thin;
}
.confirm-c-dx .confirm-card-body::-webkit-scrollbar { width: 5px; }
.confirm-c-dx .confirm-card-body::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }

.confirm-side-col {
  display: flex;
  flex-direction: column;
  gap: .3rem;
  min-width: 0;
  min-height: 0;
  overflow-y: auto;
  scrollbar-width: thin;
}
.confirm-side-col::-webkit-scrollbar { width: 5px; }
.confirm-side-col::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
.confirm-card {
  background: #fff;
  border: 1px solid #d4deea;
  border-radius: 11px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(22,70,142,.07);
  min-width: 0;
}
.confirm-card-head {
  display: flex;
  align-items: center;
  gap: .25rem;
  padding: .2rem .38rem .06rem;
}
.confirm-card-icon {
  width: 1.15rem; height: 1.15rem;
  border-radius: 6px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.ci-blue   { background: #dbeafe; color: #2563eb; }
.ci-amber  { background: #fef3c7; color: #d97706; }
.ci-violet { background: #ede9fe; color: #7c3aed; }
.ci-slate  { background: #f1f5f9; color: #475569; }
.ci-rose   { background: #fee2e2; color: #dc2626; }
.confirm-card-title { margin: 0; font-size: .62rem; font-weight: 800; }
.ct-blue   { color: #1e40af; }
.ct-amber  { color: #b45309; }
.ct-violet { color: #6d28d9; }
.ct-slate  { color: #334155; }
.ct-rose   { color: #b91c1c; }
.confirm-card-body { padding: .04rem .4rem .25rem; }
.confirm-data-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: .25rem;
  padding: .035rem 0;
  border-bottom: 1px solid #f1f5f9;
}
.confirm-data-row:last-child { border-bottom: 0; }
.confirm-data-row span { font-size: .52rem; font-weight: 600; color: #94A3B8; flex-shrink: 0; }
.confirm-data-row strong {
  font-size: .56rem;
  font-weight: 700;
  color: #1E293B;
  text-align: right;
  word-break: break-word;
  line-height: 1.2;
  min-width: 0;
}
.confirm-vital-value {
  padding: .08rem .35rem;
  border-radius: 999px;
  background: #fee2e2;
  color: #b91c1c !important;
}

.confirm-hc-card { display: flex; flex-direction: column; min-height: 0; }
.confirm-hc-body { display: flex; flex-direction: column; flex: 1; min-height: 0; }
.confirm-empty { margin: 0; font-size: .62rem; color: #94A3B8; font-style: italic; }

.confirm-dx-item {
  display: flex;
  align-items: flex-start;
  gap: 5px;
  padding: .18rem .3rem;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #eef2f7;
}
.confirm-dx-item + .confirm-dx-item { margin-top: .3rem; }
.confirm-dx-code {
  flex-shrink: 0;
  padding: .1rem .42rem;
  border-radius: 6px;
  background: #ede9fe;
  color: #6d28d9;
  font-size: .58rem;
  font-weight: 800;
  font-family: ui-monospace, monospace;
}
.confirm-dx-desc { font-size: .56rem; font-weight: 600; color: #334e70; line-height: 1.3; min-width: 0; }
.confirm-dx-principal {
  font-style: normal;
  font-size: .52rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #7c3aed;
  margin-right: .2rem;
}
.confirm-hc-box {
  flex: 1;
  min-height: 60px;
  overflow-y: auto;
  scrollbar-width: thin;
  padding: .25rem .4rem .25rem .45rem;
  border-radius: 0 8px 8px 0;
  background: #F8FAFC;
  border: 1px solid #EEF2F7;
  border-left: 3px solid #4F46E5;
  font-size: .57rem;
  font-weight: 600;
  color: #1E293B;
  line-height: 1.35;
  word-break: break-word;
  white-space: pre-line;
}
.confirm-hc-box::-webkit-scrollbar { width: 5px; }
.confirm-hc-box::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
.confirm-hc-box::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

.confirm-adjuntos-row {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem;
  min-width: 0;
}
.confirm-adjunto-chip {
  display: flex;
  align-items: center;
  gap: .35rem;
  min-width: 0;
  max-width: 100%;
  padding: .25rem .45rem .25rem .25rem;
  border-radius: 8px;
  background: #F8FAFC;
  border: 1px solid #EEF2F7;
}
.confirm-adjunto-thumb-sm, .confirm-adjunto-badge-sm {
  width: 30px; height: 30px;
  flex-shrink: 0;
  border-radius: 6px;
  object-fit: cover;
}
.confirm-adjunto-thumb-sm {
  border: 1px solid #E2E8F0;
  background: #fff;
  cursor: zoom-in;
  transition: transform .15s ease;
}
.confirm-adjunto-thumb-sm:hover { transform: scale(1.08); }
.confirm-adjunto-badge-sm {
  display: flex; align-items: center; justify-content: center;
  font-size: .5rem; font-weight: 800;
}
.confirm-adjunto-name-sm {
  font-size: .56rem; font-weight: 600; color: #64748B;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  max-width: 110px;
}

.confirm-footer {
  display: flex;
  align-items: center;
  gap: .35rem;
  padding: .3rem .55rem;
  background: #fff;
  border-top: 1px solid #F1F5F9;
  flex-shrink: 0;
}
.confirm-footer-note {
  flex: 1;
  display: flex;
  align-items: center;
  gap: .3rem;
  margin: 0;
  font-size: .5rem;
  font-weight: 500;
  color: #94A3B8;
}
.confirm-submit-btn {
  background: linear-gradient(135deg, #16468e, #2563eb) !important;
  border: none !important;
  box-shadow: 0 4px 14px rgba(22, 70, 142, .35) !important;
}
.confirm-footer :deep(.el-button) {
  height: 28px !important;
  padding: 0 10px !important;
  font-size: .62rem;
}
.confirm-submit-btn:hover {
  background: linear-gradient(135deg, #1a52a8, #3b82f6) !important;
  box-shadow: 0 6px 20px rgba(22, 70, 142, .45) !important;
}

@media (max-width: 640px) {
  .confirm-main {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: none;
    grid-auto-rows: auto;
    overflow-y: auto;
  }
  .confirm-c-paciente,
  .confirm-c-remision,
  .confirm-c-dx,
  .confirm-c-hc,
  .confirm-c-side { grid-column: auto; grid-row: auto; }
  .confirm-hc-box { min-height: 110px; max-height: 220px; flex: none; }
  .confirm-side-col { overflow-y: visible; }
}

/* ── Lightbox: vista ampliada de imagen ── */
.rf-lightbox {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .6rem;
  background: rgba(11, 20, 40, .82);
  backdrop-filter: blur(3px);
  cursor: zoom-out;
  padding: 2rem;
}
.rf-lightbox-img {
  max-width: min(90vw, 900px);
  max-height: 80vh;
  object-fit: contain;
  border-radius: 10px;
  box-shadow: 0 24px 64px rgba(0, 0, 0, .4);
  cursor: default;
}
.rf-lightbox-name {
  color: rgba(255, 255, 255, .85);
  font-size: .78rem;
  font-weight: 600;
  margin: 0;
}
.rf-lightbox-close {
  position: absolute;
  top: 1.2rem; right: 1.2rem;
  display: grid;
  place-items: center;
  width: 38px; height: 38px;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, .25);
  background: rgba(255, 255, 255, .1);
  color: #fff;
  cursor: pointer;
  transition: all .2s ease;
}
.rf-lightbox-close:hover { background: rgba(255, 255, 255, .2); }

/* ── Modal éxito ── */
:deep(.exito-dialog) { border-radius: 20px; overflow: hidden; box-shadow: 0 28px 70px rgba(11, 35, 73, .35); }
:deep(.exito-dialog .el-dialog__header) { display: none; }
:deep(.exito-dialog .el-dialog__body) { padding: 0; }
.exito-content {
  padding: 2rem 1.8rem 1.6rem;
  text-align: center;
  background: linear-gradient(180deg, #f0f5ff 0%, #f8faff 40%, #ffffff 100%);
  position: relative;
  overflow: hidden;
}
.exito-icon-circle {
  display: inline-grid;
  place-items: center;
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #22c55e, #15803d);
  color: #fff;
  box-shadow: 0 8px 24px rgba(34, 197, 94, .3);
  margin-bottom: 1rem;
}
.exito-title { font-size: 1.2rem; font-weight: 800; color: #0D2D6B; margin: 0 0 .25rem; }
.exito-subtitle { font-size: .82rem; color: #64748B; margin: 0 0 1.2rem; }
.exito-details {
  text-align: left;
  background: #fff;
  border: 1px solid #E2E8F0;
  border-radius: 12px;
  padding: .8rem 1rem;
  margin-bottom: 1.2rem;
}
.exito-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: .3rem 0;
  border-bottom: 1px solid #F1F5F9;
  font-size: .78rem;
}
.exito-detail-row:last-child { border-bottom: none; }
.exito-detail-row span { color: #94A3B8; font-weight: 600; }
.exito-detail-row strong { color: #0D2D6B; font-weight: 700; text-align: right; }
.exito-btn {
  border-radius: 10px !important;
  font-weight: 700 !important;
  height: 42px !important;
  font-size: .88rem !important;
}
.exito-actions {
  display: flex;
  gap: .6rem;
}
.exito-btn-secondary {
  border-radius: 10px !important;
  font-weight: 600 !important;
  height: 42px !important;
  font-size: .82rem !important;
  border: 1px solid #BFDBFE !important;
  background: #EFF6FF !important;
  color: #0D2D6B !important;
  display: inline-flex !important;
  align-items: center;
  transition: all .2s ease;
}
.exito-btn-secondary:hover {
  background: #DBEAFE !important;
  border-color: #60A5FA !important;
}

/* ── Confetti ── */
.exito-confetti {
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 100%;
  overflow: hidden;
  pointer-events: none;
  border-radius: 20px 20px 0 0;
}
.confetti-piece {
  position: absolute;
  width: 8px; height: 8px;
  top: -10px;
  left: calc(8.33% * var(--i) - 4%);
  border-radius: 2px;
  animation: rf-confetti-fall 1.5s cubic-bezier(.22,1,.36,1) both;
  animation-delay: calc(var(--i) * .05s);
}
.confetti-piece:nth-child(1) { background: #0D2D6B; }
.confetti-piece:nth-child(2) { background: #3B82F6; }
.confetti-piece:nth-child(3) { background: #22c55e; }
.confetti-piece:nth-child(4) { background: #f59e0b; }
.confetti-piece:nth-child(5) { background: #0D2D6B; }
.confetti-piece:nth-child(6) { background: #60A5FA; }
.confetti-piece:nth-child(7) { background: #22c55e; }
.confetti-piece:nth-child(8) { background: #3B82F6; }
.confetti-piece:nth-child(9) { background: #f59e0b; }
.confetti-piece:nth-child(10) { background: #0D2D6B; }
.confetti-piece:nth-child(11) { background: #60A5FA; }
.confetti-piece:nth-child(12) { background: #22c55e; }
@keyframes rf-confetti-fall {
  0% {
    transform: translateY(0) rotate(0deg);
    opacity: 1;
  }
  100% {
    transform: translateY(200px) rotate(360deg);
    opacity: 0;
  }
}

/* ── Section glow ── */
.rf-section-glow {
  position: absolute;
  top: -20px; right: -20px;
  width: 120px; height: 120px;
  pointer-events: none;
  opacity: .8;
}

/* ── Field stagger animation ── */
@keyframes rf-field-in {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}
.rf-field {
  animation: rf-field-in .35s cubic-bezier(.22,1,.36,1) backwards;
}
.rf-stagger-1 { animation-delay: .04s; }
.rf-stagger-2 { animation-delay: .08s; }
.rf-stagger-3 { animation-delay: .12s; }
.rf-stagger-4 { animation-delay: .16s; }
.rf-stagger-5 { animation-delay: .20s; }
.rf-stagger-6 { animation-delay: .24s; }
.rf-stagger-7 { animation-delay: .28s; }
.rf-stagger-8 { animation-delay: .32s; }

/* ── Step slide transition ── */
.step-slide-enter-active,
.step-slide-leave-active {
  transition: all .3s cubic-bezier(.22,1,.36,1);
}
.step-slide-enter-from {
  opacity: 0;
  transform: translateX(24px);
}
.step-slide-leave-to {
  opacity: 0;
  transform: translateX(-24px);
}

/* ── Fade scale transition ── */
.fade-scale-enter-active,
.fade-scale-leave-active {
  transition: all .25s cubic-bezier(.22,1,.36,1);
}
.fade-scale-enter-from,
.fade-scale-leave-to {
  opacity: 0;
  transform: scale(.85);
}

/* ── Section entrance ── */
@keyframes sp-fade-up {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
.rf-section { animation: sp-fade-up .35s ease backwards; }

/* ── Input focus glow ── */
:deep(.rf-form-col .el-input__wrapper.is-focus),
:deep(.rf-form-col .el-select__wrapper.is-focus) {
  box-shadow: 0 0 0 2px rgba(22, 70, 142, .12) !important;
}

/* ── Validación visual: check verde en campos válidos ── */
@keyframes rf-check-pop {
  from { transform: scale(.4); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
:deep(.rf-form-col .el-form-item.rf-field.is-success .el-form-item__label::after) {
  content: '✓';
  position: absolute;
  right: 1px;
  top: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 15px; height: 15px;
  border-radius: 50%;
  background: #22c55e;
  color: #fff;
  font-size: 9.5px;
  font-weight: 700;
  box-shadow: 0 0 0 2px #fff;
  animation: rf-check-pop .2s cubic-bezier(.34, 1.56, .64, 1) both;
}
:deep(.rf-form-col .el-form-item.rf-field.is-success .el-form-item__label) {
  color: #16468E;
}

/* ── Validacion visual: borde verde cuando el campo tiene valor y no hay error ── */
:deep(.rf-form-col .el-form-item:not(.is-error) .el-input__wrapper:not(.is-focus)),
:deep(.rf-form-col .el-form-item:not(.is-error) .el-select__wrapper:not(.is-focus)),
:deep(.rf-form-col .el-form-item:not(.is-error) .el-textarea__inner:not(:focus)) {
  transition: box-shadow .25s ease;
}
:deep(.rf-form-col .el-form-item.is-success .el-input__wrapper),
:deep(.rf-form-col .el-form-item.is-success .el-select__wrapper) {
  box-shadow: 0 0 0 1px #86EFAC inset !important;
}
:deep(.rf-form-col .el-form-item.is-success .el-textarea__inner) {
  border-color: #86EFAC !important;
}

/* ── Dropzone pulse when active ── */
@keyframes rf-drop-pulse {
  0%, 100% { border-color: #3B82F6; }
  50% { border-color: #60A5FA; }
}

/* ── File list transition ── */
.file-list-enter-active,
.file-list-leave-active {
  transition: all .3s cubic-bezier(.22,1,.36,1);
}
.file-list-enter-from {
  opacity: 0;
  transform: translateX(-12px);
}
.file-list-leave-to {
  opacity: 0;
  transform: translateX(12px);
}

/* ── Success modal bounce ── */
@keyframes rf-success-bounce {
  0% { transform: scale(0); opacity: 0; }
  60% { transform: scale(1.15); opacity: 1; }
  100% { transform: scale(1); }
}
.exito-icon-circle {
  animation: rf-success-bounce .5s cubic-bezier(.22,1,.36,1) both;
}

/* ── Button shimmer on hover ── */
.rf-btn-next::before, .rf-btn-submit::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.15), transparent);
  transition: left .5s ease;
}
.rf-btn-next:hover::before, .rf-btn-submit:hover::before {
  left: 100%;
}
.rf-btn-next, .rf-btn-submit {
  position: relative;
  overflow: hidden;
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .rf-grid { grid-template-columns: repeat(2, 1fr); }
  .rf-grid-trio { grid-template-columns: repeat(2, 1fr); }
  .rf-col-3 { grid-column: span 2; }
  .rf-col-3-fix { grid-column: 1 / -1; }
  .rf-col-4 { grid-column: 1 / -1; }
  .rf-col-half { grid-column: span 2; }
  .rf-col-third { grid-column: span 1; }
  .rf-col-quarter { grid-column: span 1; }
  .rf-urgencia-vital-field { grid-column: 1 / -1; }
}

@media (max-width: 640px) {
  .solicitud-page {
    min-width: 0;
    padding: .4rem;
    gap: .35rem;
  }
  .rf-stepper {
    min-width: 0;
    gap: .4rem;
    padding: .45rem .5rem;
    border-radius: 12px;
  }
  .rf-stepper-title h2 { font-size: .84rem; }
  .rf-stepper-title p { font-size: .61rem; }
  .rf-stepper-progress { gap: 5px; }
  .rf-stepper-progress-track { width: 42px; }
  .rf-stepper-progress-label { min-width: 25px; font-size: .64rem; }
  .rf-body { min-width: 0; gap: .35rem; }
  .rf-form-col { min-width: 0; padding-right: 1px; gap: .4rem; }
  .rf-bento-top .rf-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .35rem .5rem;
  }
  .rf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .rf-grid-trio { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 .5rem; }
  .rf-grid-trio > :last-child { grid-column: 1 / -1; }
  .rf-grid > *,
  .rf-grid-trio > *,
  .rf-section { min-width: 0; }
  .rf-section--compact { padding: .6rem .65rem .7rem; }
  .rf-section-header { min-width: 0; gap: .5rem; }
  .rf-section-heading { min-width: 0; }
  .rf-section-heading h3 { font-size: .78rem; }
  .rf-section-heading p { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  :deep(.rf-form-col .el-form-item__label) { font-size: .66rem; }
  :deep(.rf-form-col .el-input__wrapper),
  :deep(.rf-form-col .el-select__wrapper),
  :deep(.rf-form-col .el-textarea__inner) { min-width: 0; }
}
</style>

<style>
/* El modal de registro de clínica se teleporta a <body> — estos overrides
   viven en el bloque global para que el header azul quede pegado a los
   bordes redondeados del diálogo (sin el marco blanco del padding por
   defecto de el-dialog). */
.clinica-dialog.el-dialog {
  padding: 0 !important;
  border-radius: 20px;
  overflow: hidden;
}
.clinica-dialog .el-dialog__header { display: none; }
.clinica-dialog .el-dialog__body { padding: 0 !important; }
.clinica-dialog .el-dialog__footer { padding: 0 !important; }

/* ── Select dropdown personalizado ── */
.rf-select-popper {
  border-radius: 14px !important;
  border: 1px solid #DBEAFE !important;
  box-shadow: 0 12px 40px rgba(13, 45, 107, .15) !important;
  overflow: hidden;
  padding: 4px !important;
}
/* El popper se teleporta a <body>, fuera del zoom:0.8 de #app. El zoom va
   en el dropdown interno (no en el popper) porque si no también escala el
   top/left que Popper escribe y el listado queda desplazado hacia arriba. */
.rf-select-popper .el-select-dropdown {
  zoom: 0.8;
}
.rf-select-popper .el-select-dropdown__item {
  border-radius: 8px;
  margin: 2px 0;
  padding: 0 .7rem;
  font-size: .8rem;
  font-weight: 500;
  color: #475569;
  transition: all .18s ease;
  position: relative;
}
.rf-select-popper .el-select-dropdown__item:hover {
  background: #EFF6FF;
  color: #0D2D6B;
  padding-left: .9rem;
}
.rf-select-popper .el-select-dropdown__item.selected {
  background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
  color: #0D2D6B;
  font-weight: 700;
}
.rf-select-popper .el-select-dropdown__item.selected::after {
  content: '';
  position: absolute;
  right: .6rem;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #0D2D6B;
}
.rf-select-popper .el-select-dropdown__item.selected.hover {
  background: linear-gradient(135deg, #BFDBFE, #93C5FD);
}
.rf-select-popper .el-select-dropdown__empty {
  padding: .8rem;
  font-size: .75rem;
  color: #94A3B8;
}
.rf-select-popper .el-select-dropdown__loading {
  padding: .8rem;
  font-size: .75rem;
  color: #94A3B8;
}
/* Scrollbar del dropdown */
.rf-select-popper .el-select-dropdown__wrap {
  max-height: 240px;
}
.rf-select-popper .el-scrollbar__bar.is-vertical {
  width: 4px;
  right: 2px;
}
.rf-select-popper .el-scrollbar__thumb {
  background: #BFDBFE;
  border-radius: 4px;
}
.rf-select-popper .el-scrollbar__thumb:hover {
  background: #60A5FA;
}
/* Input de busqueda (filterable) */
.rf-select-popper .el-select-dropdown__option-item {
  border-radius: 8px;
}
</style>
