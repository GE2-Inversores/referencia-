<template>
  <div class="evo-page h-full overflow-y-auto p-4 sm:p-5 animate-fade-in-up"
    :class="{ 'evo-page-results': hasSearched }"
    style="animation-duration: 0.4s; animation-fill-mode: both;">

    <main class="evo-workspace" :class="{ 'evo-workspace-results': hasSearched }">
      <form
        v-if="!hasSearched"
        class="evo-lookup-card"
        @submit.prevent="buscarPaciente"
      >
        <div class="evo-lookup-icon"><component :is="ClipboardIcon" /></div>
        <h1 class="evo-lookup-title">Evolución de pacientes</h1>
        <p class="evo-lookup-subtitle">Consulte la evolución de un paciente con referencia pendiente.</p>
        <el-input
          v-model="busqueda"
          placeholder="ID de la referencia o cédula..."
          :prefix-icon="SearchIcon"
          clearable
          size="large"
          aria-label="ID de la referencia o cédula del paciente"
        />
        <button class="evo-lookup-submit" type="submit" :disabled="cargando || busqueda.trim().length < 3">
          <component :is="cargando ? LoaderIcon : SearchIcon" :class="{ 'animate-spin': cargando }" />
          {{ cargando ? 'Buscando...' : 'Buscar' }}
        </button>
      </form>

      <!-- ── Cargando ── -->
      <div v-if="cargando && hasSearched" class="evo-results-list">
        <div v-for="i in 3" :key="i" class="evo-card rounded-2xl p-4 space-y-2.5">
          <div class="flex items-center gap-3">
            <div class="skeleton-circle"></div>
            <div class="flex-1 space-y-1.5">
              <div class="skeleton-bar" style="width:35%; height:11px;"></div>
              <div class="skeleton-bar" style="width:55%; height:9px;"></div>
            </div>
          </div>
          <div class="skeleton-bar" style="width:100%; height:44px; border-radius:10px;"></div>
        </div>
      </div>

      <!-- ── Vacío ── -->
      <div v-else-if="hasSearched && solicitudesFiltradas.length === 0" class="evo-login-prompt">
        <div class="evo-empty-icon"><component :is="busqueda ? SearchIcon : ActivityIcon" class="w-7 h-7" /></div>
        <p class="evo-empty-title">{{ tituloVacio }}</p>
        <p class="evo-empty-sub">{{ subtituloVacio }}</p>
        <button class="evo-detail-footer-btn evo-empty-retry" type="button" @click="nuevaBusqueda">
          <component :is="SearchIcon" class="w-3.5 h-3.5" /> Buscar de nuevo
        </button>
      </div>

      <!-- ── Pacientes ── -->
      <div v-else-if="hasSearched && !cargando" class="evo-results-list">
        <article v-for="sol in solicitudesFiltradas" :id="`evo-card-${sol.id}`" :key="sol.id" class="evo-card evo-pt-card anim-slide-up">
          <!-- Paciente + estado -->
          <header class="evo-pt-head">
            <div class="evo-avatar evo-avatar-lg">{{ inicialesPaciente(sol) }}</div>
            <div class="evo-pt-id">
              <h2>{{ nombrePaciente(sol) }}</h2>
              <p>{{ sol.tipo_documento }} {{ sol.numero_documento }} · {{ sol.eps || '—' }}</p>
            </div>
            <div class="evo-pt-chips">
              <span class="evo-id-chip">{{ formatId(sol.id) }}</span>
              <span class="evo-estado-chip" :class="'estado-' + sol.estado">{{ estadoLabel(sol.estado) }}</span>
              <span v-if="sol.urgencia_vital" class="evo-urgencia-chip">URGENCIA VITAL</span>
              <span class="evo-dias-chip" :title="'Remitido el ' + formatFecha(sol.created_at)">
                <component :is="ClockIcon" class="w-3 h-3" />
                {{ diasEsperando(sol) }}
              </span>
            </div>
          </header>

          <div class="evo-pt-scroll">
            <div class="evo-detail-top">
              <!-- Información del paciente -->
              <section class="evo-panel evo-panel-blue">
                <div class="evo-panel-title"><span class="evo-panel-icon"><component :is="UserIcon" class="w-4 h-4" /></span> Información del paciente</div>
                <div class="evo-info-rows">
                  <div class="evo-info-row"><span>Documento</span><strong>{{ sol.tipo_documento }} {{ sol.numero_documento }}</strong></div>
                  <div class="evo-info-row"><span>Edad / Género</span><strong>{{ sol.edad ? `${sol.edad} años` : '—' }} · {{ sol.genero === 'F' ? 'Femenino' : sol.genero === 'M' ? 'Masculino' : '—' }}</strong></div>
                  <div class="evo-info-row"><span>EPS</span><strong>{{ sol.eps || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Municipio</span><strong>{{ sol.municipio_capita || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Especialidad</span><strong>{{ sol.especialidad_requerida || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Servicio actual</span><strong>{{ sol.servicio_ubicacion_actual || '—' }}</strong></div>
                </div>
              </section>

              <!-- Información de la solicitud -->
              <section class="evo-panel evo-panel-amber">
                <div class="evo-panel-title"><span class="evo-panel-icon"><component :is="BuildingIcon" class="w-4 h-4" /></span> Información de la solicitud</div>
                <div class="evo-info-rows">
                  <div class="evo-info-row"><span>Fecha y hora</span><strong>{{ formatFecha(sol.fecha || sol.created_at) }} · {{ sol.hora ? formatHora(sol.hora) : '—' }}</strong></div>
                  <div class="evo-info-row"><span>Institución</span><strong>{{ sol.clinica?.nombre || sol.institucion_remitente || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Remite</span><strong>{{ sol.quien_remitente || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Teléfono</span><strong>{{ sol.telefono_contacto || sol.telefono_paciente || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Correo</span><strong>{{ sol.correo_contacto || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Tipo referencia</span><strong>{{ sol.tipo_referencia || '—' }}</strong></div>
                  <div class="evo-info-row"><span>Llegada esperada</span><strong>{{ sol.hora_llegada ? `${formatHora(sol.hora_llegada)}${sol.lugar_llegada ? ' · ' + sol.lugar_llegada : ''}` : '—' }}</strong></div>
                </div>
              </section>

              <!-- Diagnósticos + datos adicionales -->
              <div class="evo-detail-side">
                <section class="evo-panel evo-panel-violet">
                  <div class="evo-panel-title"><span class="evo-panel-icon"><component :is="StethoscopeIcon" class="w-4 h-4" /></span> Diagnósticos</div>
                  <div v-if="sol.diagnosticos?.length" class="evo-diagnosis-list">
                    <div v-for="(dx, index) in sol.diagnosticos" :key="dx.id || index" class="evo-diagnosis">
                      <span class="evo-diagnosis-code">{{ dx.codigo_cie10 || '—' }}</span>
                      <span class="evo-diagnosis-description">{{ dx.descripcion }}</span>
                      <span v-if="index === 0" class="evo-principal-chip">Principal</span>
                    </div>
                  </div>
                  <p v-else class="evo-detail-paragraph">{{ sol.diagnostico || 'Sin diagnóstico registrado' }}</p>
                </section>
              </div>
            </div>

            <div class="evo-detail-bottom">
              <!-- Evolución de hoy + historial -->
              <section class="evo-panel evo-panel-clinical">
                <div class="evo-panel-title">
                  <span class="evo-panel-icon"><component :is="ClipboardIcon" class="w-4 h-4" /></span>
                  Evolución de hoy — {{ hoyLabel }}
                  <span v-if="tieneNotaHoy(sol)" class="evo-nota-hoy"><component :is="CheckCircleIcon" class="w-3 h-3" /> Ya registrada hoy</span>
                </div>
                <textarea
                  :id="`evo-nota-${sol.id}`"
                  v-model="drafts[sol.id]"
                  rows="2"
                  maxlength="2000"
                  class="evo-textarea evo-textarea-clinical"
                  placeholder="Describa la evolución del paciente: estado clínico, signos, procedimientos, observaciones…"
                ></textarea>

                <!-- Adjuntar soportes con la evolución (p. ej. la HC escaneada) -->
                <div class="evo-attach">
                  <input
                    :ref="(el) => setSoporteInput(sol.id, el)"
                    type="file"
                    class="hidden"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tiff,.svg,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar"
                    @change="agregarSoportes(sol.id, $event)"
                  />
                  <button type="button" class="evo-attach-btn" @click="soporteInputs[sol.id]?.click()">
                    <component :is="PaperclipIcon" class="w-3.5 h-3.5" /> Adjuntar soporte
                  </button>
                  <span class="evo-attach-hint">HC, PDF o imágenes · hasta 5 archivos de 10 MB</span>
                </div>
                <div v-if="soportesDraft[sol.id]?.length" class="evo-attach-list">
                  <span v-for="(archivo, i) in soportesDraft[sol.id]" :key="`${archivo.name}-${i}`" class="evo-attach-chip">
                    <component :is="PaperclipIcon" class="w-3 h-3" />
                    <span class="evo-attach-name" :title="archivo.name">{{ archivo.name }}</span>
                    <small>{{ formatTamano(archivo.size) }}</small>
                    <button type="button" class="evo-attach-del" aria-label="Quitar archivo" @click="soportesDraft[sol.id].splice(i, 1)">
                      <component :is="XIcon" class="w-3 h-3" />
                    </button>
                  </span>
                </div>

                <div class="evo-history-box evo-history-box-compact">
                  <div class="evo-history-title"><component :is="ClockIcon" class="w-3.5 h-3.5" /> Historial de evolución</div>
                  <template v-if="sol.evoluciones?.length">
                    <div v-for="nota in sol.evoluciones" :key="nota.id" class="evo-nota">
                      <div class="evo-nota-dot"></div>
                      <div class="evo-nota-body">
                        <div class="evo-nota-fecha">
                          <component :is="CalendarIcon" class="w-3 h-3" />
                          {{ formatFecha(nota.created_at) }} · {{ formatHoraNota(nota.created_at) }}
                        </div>
                        <p class="evo-nota-texto">{{ nota.nota }}</p>
                      </div>
                    </div>
                  </template>
                  <p v-else class="evo-sin-notas">Sin evoluciones registradas todavía — anote la primera arriba.</p>
                </div>
              </section>

              <div class="evo-detail-side">
                <!-- Datos adicionales -->
                <section class="evo-panel evo-panel-slate">
                  <div class="evo-panel-title"><span class="evo-panel-icon"><component :is="ActivityIcon" class="w-4 h-4" /></span> Datos adicionales</div>
                  <div class="evo-extra-grid">
                    <div class="evo-extra-card evo-extra-warn"><span>Condición especial</span><strong>{{ sol.condicion_especial || 'Ninguna' }}</strong></div>
                    <div class="evo-extra-card evo-extra-danger"><span>Aislamiento</span><strong>{{ sol.requiere_aislamiento ? (sol.tipo_aislamiento || 'Requerido') : 'No requiere' }}</strong></div>
                    <div class="evo-extra-card evo-extra-info"><span>Número de soportes</span><strong>{{ sol.adjuntos?.length || 0 }} soporte(s)</strong></div>
                    <div class="evo-extra-card evo-extra-info"><span>Estado actual</span><strong>{{ estadoLabel(sol.estado) }}<template v-if="sol.hora_respuesta"> · {{ formatHora(sol.hora_respuesta) }}</template></strong></div>
                  </div>
                </section>

                <!-- Soportes y archivos -->
                <section class="evo-panel evo-panel-red">
                  <div class="evo-panel-title"><span class="evo-panel-icon"><component :is="PaperclipIcon" class="w-4 h-4" /></span> Soportes y archivos <span class="evo-count">{{ sol.adjuntos?.length || 0 }}</span></div>
                  <div v-if="sol.adjuntos?.length" class="evo-attachments">
                    <div v-for="adjunto in sol.adjuntos" :key="adjunto.id" class="evo-attachment">
                      <span class="evo-attachment-type">{{ extensionArchivo(adjunto.nombre_original) }}</span>
                      <span class="evo-attachment-name">{{ adjunto.nombre_original }}</span>
                      <small>{{ formatTamano(adjunto.tamano) }}</small>
                    </div>
                  </div>
                  <p v-else class="evo-no-attachments">Esta solicitud no tiene soportes adjuntos.</p>
                </section>
              </div>
            </div>
          </div>

          <footer class="evo-pt-footer">
            <span><component :is="ShieldIcon" class="w-3.5 h-3.5" /> Información clínica confidencial</span>
            <div class="evo-pt-actions">
              <button class="evo-detail-footer-btn evo-detail-footer-btn-light" type="button" @click="nuevaBusqueda">
                <component :is="SearchIcon" class="w-3.5 h-3.5" /> Nueva búsqueda
              </button>
              <button
                class="evo-detail-footer-btn"
                type="button"
                :disabled="(!drafts[sol.id]?.trim() && !soportesDraft[sol.id]?.length) || guardando.has(sol.id)"
                @click="guardarNota(sol)"
              >
                <component :is="guardando.has(sol.id) ? LoaderIcon : SendIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': guardando.has(sol.id) }" />
                {{ guardando.has(sol.id) ? 'Guardando…' : 'Registrar evolución' }}
              </button>
            </div>
          </footer>
        </article>
      </div>
    </main>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue';
import { Search as SearchIcon, Clock as ClockIcon, Activity as ActivityIcon, CheckCircle as CheckCircleIcon, Send as SendIcon, Loader2 as LoaderIcon, Calendar as CalendarIcon, User as UserIcon, Building2 as BuildingIcon, Stethoscope as StethoscopeIcon, ClipboardList as ClipboardIcon, Paperclip as PaperclipIcon, ShieldCheck as ShieldIcon, X as XIcon } from '@lucide/vue';
import { useRoute } from 'vue-router';
import { ElInput } from 'element-plus';
import http from '@/plugins/axios';
import notify from '@/plugins/toast';
import { useClinicaAuthStore } from '@/stores/clinicaAuth';

interface Evolucion {
  id: number;
  nota: string;
  created_at: string;
}

interface Solicitud {
  id: number;
  primer_nombre: string;
  segundo_nombre?: string;
  primer_apellido: string;
  segundo_apellido?: string;
  tipo_documento: string;
  numero_documento: string;
  edad?: number;
  genero?: string;
  eps: string;
  fecha?: string;
  hora?: string;
  telefono_paciente?: string;
  direccion_paciente?: string;
  municipio_capita?: string;
  especialidad_requerida: string;
  tipo_referencia?: string;
  servicio_ubicacion_actual?: string;
  institucion_remitente?: string;
  quien_remitente?: string;
  telefono_contacto?: string;
  correo_contacto?: string;
  clinica?: { nombre?: string };
  estado: string;
  urgencia_vital?: boolean;
  hora_llegada?: string;
  hora_respuesta?: string;
  lugar_llegada?: string;
  sede?: string;
  diagnostico?: string;
  diagnosticos?: { id?: number; codigo_cie10?: string; descripcion: string }[];
  resumen_historia_clinica?: string;
  observaciones?: string;
  condicion_especial?: string;
  requiere_aislamiento?: boolean;
  tipo_aislamiento?: string;
  created_at: string;
  adjuntos?: { id: number; nombre_original: string; mime_type?: string; tamano: number }[];
  evoluciones?: Evolucion[];
}

const clinicaAuth = useClinicaAuthStore();
const logoColor = '/images/logo-avatar.png';

const solicitudes = ref<Solicitud[]>([]);
const route = useRoute();
const cargando = ref(false);
const busqueda = ref('');
const busquedaAplicada = ref('');
const hasSearched = ref(false);
const drafts = reactive<Record<number, string>>({});
const soportesDraft = reactive<Record<number, File[]>>({});
const soporteInputs = reactive<Record<number, HTMLInputElement>>({});
const guardando = reactive(new Set<number>());

function setSoporteInput(id: number, el: unknown): void {
  if (el instanceof HTMLInputElement) soporteInputs[id] = el;
}

function agregarSoportes(id: number, event: Event): void {
  const input = event.target as HTMLInputElement;
  const archivos = [...(input.files ?? [])];
  input.value = '';

  soportesDraft[id] = soportesDraft[id] ?? [];
  for (const archivo of archivos) {
    if (soportesDraft[id].length >= 5) {
      notify.warning('Máximo 5 soportes por evolución');
      break;
    }
    if (archivo.size > 10 * 1024 * 1024) {
      notify.warning(`"${archivo.name}" supera los 10 MB`);
      continue;
    }
    soportesDraft[id].push(archivo);
  }
}

const hoyLabel = new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });

// La consulta se ejecuta al enviar el formulario, no mientras se escribe.
const solicitudesFiltradas = computed(() => {
  if (!clinicaAuth.isAuthenticated) return solicitudes.value;
  const term = busquedaAplicada.value.trim().toLowerCase();
  if (!term) return solicitudes.value;
  const digitos = term.replace(/\D/g, '');
  return solicitudes.value.filter((sol) => {
    if (sol.numero_documento?.toLowerCase().includes(term)) return true;
    if (formatId(sol.id).toLowerCase().includes(term)) return true;
    if (digitos && String(sol.id) === String(Number(digitos))) return true;
    return false;
  });
});

const tituloVacio = computed(() => {
  return 'Sin resultados';
});

const subtituloVacio = computed(() => {
  return 'No encontramos una referencia pendiente con ese ID o número de cédula. Verifique los datos e inténtelo nuevamente.';
});

function formatId(id: number): string {
  return 'ID' + String(id).padStart(6, '0');
}

function inicialesPaciente(sol: Solicitud): string {
  return `${sol.primer_nombre?.[0] ?? ''}${sol.primer_apellido?.[0] ?? ''}`.toUpperCase();
}

function nombrePaciente(sol: Solicitud): string {
  return [sol.primer_nombre, sol.segundo_nombre, sol.primer_apellido, sol.segundo_apellido].filter(Boolean).join(' ');
}

function estadoLabel(estado: string): string {
  return { pendiente: 'Pendiente', pendiente_cama: 'Esperando cama', en_espera: 'Aceptado' }[estado] ?? estado;
}

function diasEsperando(sol: Solicitud): string {
  const dias = Math.max(0, Math.floor((Date.now() - new Date(sol.created_at).getTime()) / 86400000));
  return dias === 0 ? 'Hoy' : dias === 1 ? '1 día' : `${dias} días`;
}

function formatFecha(fecha: string): string {
  if (!fecha) return '—';
  const d = new Date(fecha);
  if (isNaN(d.getTime())) return fecha;
  return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatHoraNota(fecha: string): string {
  return new Date(fecha).toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function formatHora(hora: string): string {
  if (!hora) return '';
  const [hours, minutes] = hora.split(':').map(Number);
  if (Number.isNaN(hours) || Number.isNaN(minutes)) return hora;
  const ampm = hours >= 12 ? 'p. m.' : 'a. m.';
  const hour12 = hours % 12 || 12;
  return `${hour12}:${String(minutes).padStart(2, '0')} ${ampm}`;
}

function extensionArchivo(nombre: string): string {
  return nombre.split('.').pop()?.slice(0, 4).toUpperCase() || 'FILE';
}

function formatTamano(bytes: number): string {
  if (!bytes) return '—';
  return bytes >= 1024 * 1024
    ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function tieneNotaHoy(sol: Solicitud): boolean {
  const hoy = new Date().toDateString();
  return (sol.evoluciones ?? []).some((n) => new Date(n.created_at).toDateString() === hoy);
}

async function cargar() {
  cargando.value = true;
  try {
    const { data } = await http.get('/api/externo/evoluciones', {
      params: { buscar: busquedaAplicada.value },
      headers: { 'X-Skip-Auth-Redirect': '1' },
    });
    solicitudes.value = data.data;
  } catch {
    notify.error('No se pudieron cargar los pacientes pendientes');
  } finally {
    cargando.value = false;
  }
}

async function buscarPaciente() {
  const query = busqueda.value.trim();
  if (query.length < 3) {
    notify.warning('Escriba un ID de referencia o una cédula de al menos 3 caracteres');
    return;
  }
  busquedaAplicada.value = query;
  hasSearched.value = true;
  await cargar();
}

function nuevaBusqueda(): void {
  busqueda.value = '';
  busquedaAplicada.value = '';
  solicitudes.value = [];
  hasSearched.value = false;
}

async function guardarNota(sol: Solicitud) {
  const nota = drafts[sol.id]?.trim();
  const soportes = soportesDraft[sol.id] ?? [];
  if (!nota && soportes.length === 0) return;
  guardando.add(sol.id);
  try {
    const body = new FormData();
    if (nota) body.append('nota', nota);
    soportes.forEach((soporte) => body.append('soportes[]', soporte));
    const { data } = await http.post(`/api/externo/solicitudes/${sol.id}/evoluciones`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (data.solicitud) {
      sol.evoluciones = data.solicitud.evoluciones ?? sol.evoluciones;
      sol.adjuntos = data.solicitud.adjuntos ?? sol.adjuntos;
    } else {
      sol.evoluciones = [data.data, ...(sol.evoluciones ?? [])];
    }
    drafts[sol.id] = '';
    soportesDraft[sol.id] = [];
    notify.success('Evolución registrada');
  } catch (e: any) {
    notify.error(e?.response?.data?.message ?? 'No se pudo registrar la evolución');
  } finally {
    guardando.delete(sol.id);
  }
}

onMounted(async () => {
  if (!clinicaAuth.isHydrated) {
    await clinicaAuth.fetchClinica();
  }

  // Enlace del correo de "Solicitar evolución": /clinica/evolucion?id=865
  const idEnlace = Number(route.query.id);
  if (Number.isInteger(idEnlace) && idEnlace > 0) {
    busqueda.value = formatId(idEnlace);
    await buscarPaciente();
  }
});
</script>

<style scoped>
.evo-page {
  background:
    radial-gradient(ellipse at 12% 18%, rgba(108, 157, 238, .17), transparent 38%),
    radial-gradient(ellipse at 88% 82%, rgba(70, 133, 221, .11), transparent 34%),
    linear-gradient(145deg, #f5f8ff 0%, #eaf1ff 52%, #f2f6ff 100%);
}
.dark .evo-page {
  background:
    radial-gradient(ellipse at 12% 18%, rgba(37, 99, 235, .13), transparent 38%),
    linear-gradient(145deg, #0c1423 0%, #111c30 52%, #0d1728 100%);
}
.evo-workspace {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 18px;
  padding: 0 0 76px;
}
.evo-workspace-results {
  align-items: center;
  justify-content: center;
  padding-bottom: 24px;
}
.evo-lookup-card {
  position: relative;
  isolation: isolate;
  display: flex;
  width: min(440px, 100%);
  flex-direction: column;
  align-items: center;
  padding: 22px 30px 27px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, .95);
  border-radius: 22px;
  background: rgba(255, 255, 255, .76);
  box-shadow: 0 14px 30px rgba(35, 74, 139, .15), 0 3px 8px rgba(35, 74, 139, .06);
  backdrop-filter: blur(14px);
  text-align: center;
}
.evo-lookup-card::before {
  position: absolute;
  z-index: -1;
  top: 0;
  right: -16px;
  width: 105px;
  height: 62px;
  border-radius: 0 0 0 100%;
  background: linear-gradient(135deg, rgba(125, 211, 252, .12), rgba(56, 162, 239, .56));
  content: '';
  transform: rotate(-5deg);
}
.dark .evo-lookup-card {
  border-color: rgba(125, 153, 197, .25);
  background: rgba(17, 29, 49, .86);
  box-shadow: 0 16px 38px rgba(0, 0, 0, .28);
}
.evo-lookup-icon {
  display: grid;
  width: 68px;
  height: 68px;
  place-items: center;
  margin-bottom: 11px;
  border: 1px solid #e1ebff;
  border-radius: 19px;
  background: linear-gradient(145deg, #fff, #eff5ff);
  color: #0754b5;
  box-shadow: 0 5px 14px rgba(37, 99, 235, .13);
}
.evo-lookup-icon :deep(svg) { width: 38px; height: 38px; stroke-width: 1.8; }
.dark .evo-lookup-icon { border-color: #29466e; background: #172c4b; color: #77b8ff; }
.evo-lookup-title {
  margin: 0;
  color: #0d285a;
  font-size: 21px;
  font-weight: 850;
  letter-spacing: -.035em;
  line-height: 1.25;
}
.dark .evo-lookup-title { color: #e6f0ff; }
.evo-lookup-subtitle {
  max-width: 300px;
  margin: 7px 0 19px;
  color: #7a8dac;
  font-size: 12px;
  line-height: 1.5;
}
.dark .evo-lookup-subtitle { color: #a0b2cc; }
.evo-lookup-card :deep(.el-input) { width: 100%; }
.evo-lookup-card :deep(.el-input__wrapper) {
  min-height: 42px;
  padding: 0 13px;
  border: 1px solid #c8d7f0;
  border-radius: 12px;
  background: rgba(255, 255, 255, .72);
  box-shadow: none;
}
.evo-lookup-card :deep(.el-input__wrapper.is-focus) {
  border-color: #3984df;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}
.evo-lookup-card :deep(.el-input__inner) { color: #334b73; font-size: 12px; }
.evo-lookup-card :deep(.el-input__inner::placeholder) { color: #7f91b0; }
.evo-lookup-card :deep(.el-input__prefix-inner) { color: #0754b5; }
.dark .evo-lookup-card :deep(.el-input__wrapper) { border-color: #385477; background: #101d31; }
.dark .evo-lookup-card :deep(.el-input__inner) { color: #e2e8f0; }
.dark .evo-lookup-card :deep(.el-input__prefix-inner) { color: #7dbbff; }
.evo-lookup-submit {
  display: flex;
  width: 100%;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 17px;
  border: 1px solid #0754b5;
  border-radius: 11px;
  background: linear-gradient(110deg, #0754b5, #06469d);
  color: #fff;
  box-shadow: 0 5px 12px rgba(7, 84, 181, .2);
  cursor: pointer;
  font-size: 13px;
  font-weight: 800;
  transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
}
.evo-lookup-submit :deep(svg) { width: 15px; height: 15px; }
.evo-lookup-submit:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 16px rgba(7, 84, 181, .25); filter: brightness(1.05); }
.evo-lookup-submit:disabled { cursor: not-allowed; opacity: .6; }
.evo-lookup-card-compact {
  display: grid;
  width: min(560px, 100%);
  grid-template-columns: 38px minmax(0, 1fr) 112px;
  grid-template-areas: "icon title title" "input input btn";
  align-items: center;
  gap: 8px 10px;
  padding: 12px 16px;
  text-align: left;
}
.evo-lookup-card-compact .evo-lookup-icon { grid-area: icon; }
.evo-lookup-card-compact .evo-lookup-title { grid-area: title; }
.evo-lookup-card-compact :deep(.el-input) { grid-area: input; }
.evo-lookup-card-compact .evo-lookup-submit { grid-area: btn; }
.evo-lookup-card-compact::before { display: none; }
.evo-lookup-card-compact .evo-lookup-icon { width: 36px; height: 36px; margin: 0; border-radius: 11px; }
.evo-lookup-card-compact .evo-lookup-icon :deep(svg) { width: 21px; height: 21px; }
.evo-lookup-card-compact .evo-lookup-title { font-size: 14px; }
.evo-lookup-card-compact .evo-lookup-subtitle { display: none; }
.evo-lookup-card-compact :deep(.el-input__wrapper) { min-height: 36px; }
.evo-lookup-card-compact .evo-lookup-submit { min-height: 36px; margin: 0; }
.evo-results-list { display: flex; flex-direction: column; align-items: center; gap: 12px; width: 100%; }

/* ── Tarjeta pequeña y centrada del paciente (mismo estilo de vidrio del buscador) ── */
.evo-card.evo-pt-card {
  position: relative;
  isolation: isolate;
  display: flex;
  width: min(900px, 100%);
  flex-direction: column;
  gap: 12px;
  padding: 18px 22px 16px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, .95);
  border-radius: 22px;
  background: rgba(255, 255, 255, .82);
  box-shadow: 0 14px 30px rgba(35, 74, 139, .15), 0 3px 8px rgba(35, 74, 139, .06);
  backdrop-filter: blur(14px);
}
.evo-card.evo-pt-card::before {
  position: absolute;
  z-index: -1;
  top: 0;
  right: -16px;
  width: 105px;
  height: 62px;
  border-radius: 0 0 0 100%;
  background: linear-gradient(135deg, rgba(125, 211, 252, .12), rgba(56, 162, 239, .5));
  content: '';
  transform: rotate(-5deg);
}
.evo-card.evo-pt-card:hover { transform: none; box-shadow: 0 18px 38px rgba(35, 74, 139, .18), 0 3px 8px rgba(35, 74, 139, .06); }
.dark .evo-card.evo-pt-card { border-color: rgba(125, 153, 197, .25); background: rgba(17, 29, 49, .88); box-shadow: 0 16px 38px rgba(0, 0, 0, .28); }
.evo-pt-head { display: flex; align-items: center; gap: 14px; padding-right: 36px; }
.evo-avatar-lg { width: 52px; height: 52px; border-radius: 14px; font-size: 16px; }
.evo-pt-id { min-width: 0; flex: 1; }
.evo-pt-id h2 { margin: 0; overflow: hidden; color: #0d285a; font-size: 17px; font-weight: 850; letter-spacing: -.01em; text-overflow: ellipsis; white-space: nowrap; }
.evo-pt-id p { margin: 3px 0 0; overflow: hidden; color: #7a8dac; font-size: 11.5px; text-overflow: ellipsis; white-space: nowrap; }
.dark .evo-pt-id h2 { color: #e6f0ff; }
.dark .evo-pt-id p { color: #a0b2cc; }
.evo-pt-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 7px; }
.evo-pt-scroll {
  display: flex;
  max-height: calc(100vh - 200px);
  min-height: 180px;
  flex-direction: column;
  gap: 10px;
  padding-right: 3px;
  overflow-y: auto;
  scrollbar-width: thin;
}
.evo-pt-card .evo-detail-side .evo-panel { padding: 10px 12px; }
.evo-extra-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
.evo-extra-card { min-width: 0; padding: 7px 9px; border: 1px solid transparent; border-radius: 10px; }
.evo-extra-card span { display: block; font-size: 9px; font-weight: 700; }
.evo-extra-card strong { display: block; margin-top: 3px; overflow: hidden; font-size: 10.5px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.evo-extra-warn { border-color: #fde3b0; background: #fff7e6; color: #b45309; }
.evo-extra-danger { border-color: #fecdd3; background: #fff1f2; color: #be123c; }
.evo-extra-info { border-color: #dbeafe; background: #eff6ff; color: #1d4ed8; }
.dark .evo-extra-warn { border-color: #62451c; background: #2c2416; color: #fdc979; }
.dark .evo-extra-danger { border-color: #6b2434; background: #2b1620; color: #fda4af; }
.dark .evo-extra-info { border-color: #31527c; background: #172b47; color: #93c5fd; }
.evo-textarea-clinical { width: 100%; min-height: 64px; margin-bottom: 10px; border-color: #a9ccff; font-size: 12px; }
.evo-attach { display: flex; align-items: center; gap: 10px; margin: -2px 0 8px; }
.evo-attach-btn {
  display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;
  padding: 6px 12px; border: 1.5px dashed #a9ccff; border-radius: 9px;
  background: #f2f8ff; color: #0754b5; font-size: 11px; font-weight: 800;
  cursor: pointer; transition: all .15s ease;
}
.evo-attach-btn:hover { background: #e4f0ff; border-color: #5b9dff; }
.evo-attach-hint { color: #90a6c4; font-size: 9.5px; line-height: 1.3; }
.evo-attach-list { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.evo-attach-chip {
  display: inline-flex; align-items: center; gap: 6px; max-width: 100%;
  padding: 4px 8px; border: 1px solid #d4e2f5; border-radius: 8px;
  background: #fff; color: #2d4a73; font-size: 10.5px; font-weight: 600;
}
.evo-attach-name { overflow: hidden; max-width: 160px; text-overflow: ellipsis; white-space: nowrap; }
.evo-attach-chip small { color: #8ba3c9; font-size: 9px; }
.evo-attach-del {
  display: grid; place-items: center; width: 16px; height: 16px; flex-shrink: 0;
  border: 0; border-radius: 50%; background: #fee2e2; color: #dc2626; cursor: pointer;
}
.evo-attach-del:hover { background: #fecaca; }
.dark .evo-attach-btn { border-color: #31527c; background: #172b47; color: #93c5fd; }
.dark .evo-attach-btn:hover { border-color: #4d8ef7; background: #1e3a5f; }
.dark .evo-attach-hint { color: #6d84a8; }
.dark .evo-attach-chip { border-color: #314666; background: #1c2d46; color: #bcd5f7; }
.dark .evo-attach-del { background: #4c1d24; color: #fda4af; }
.evo-history-box-compact { display: block; max-height: 130px; padding: 9px 12px; overflow-y: auto; }
.evo-history-title { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; color: #0754b5; font-size: 11px; font-weight: 850; }
.dark .evo-history-title { color: #7dbbff; }
.evo-pt-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding-top: 12px; border-top: 1px solid #e3ebf7; }
.dark .evo-pt-footer { border-color: #263352; }
.evo-pt-footer > span { display: inline-flex; align-items: center; gap: 6px; color: #657795; font-size: 10px; }
.evo-pt-actions { display: flex; align-items: center; gap: 10px; }
.evo-empty-retry { margin-top: 14px; }
.evo-detail-footer-btn:disabled { opacity: .45; cursor: not-allowed; box-shadow: none; transform: none; filter: none; }
/* Versión compacta: toda la ficha cabe en una sola vista */
.evo-page.evo-page-results { padding: 8px; }
.evo-workspace-results { padding-bottom: 0; }
.evo-card.evo-pt-card { padding: 12px 16px 10px; gap: 8px; }
.evo-pt-card .evo-pt-head { gap: 10px; }
.evo-pt-card .evo-avatar-lg { width: 40px; height: 40px; border-radius: 12px; font-size: 14px; }
.evo-pt-card .evo-pt-id h2 { font-size: 15px; }
.evo-pt-card .evo-pt-id p { margin-top: 1px; font-size: 10.5px; }
.evo-pt-card .evo-pt-chips { flex-shrink: 0; margin: 0 40px 0 auto; }
.evo-pt-card .evo-pt-scroll { gap: 8px; min-height: 0; max-height: calc(100vh - 150px); }
.evo-pt-card .evo-detail-top,
.evo-pt-card .evo-detail-bottom,
.evo-pt-card .evo-detail-side { gap: 8px; }
.evo-pt-card .evo-panel,
.evo-pt-card .evo-detail-side .evo-panel { padding: 8px 10px; border-radius: 12px; }
.evo-pt-card .evo-panel-title { gap: 6px; margin-bottom: 5px; font-size: 11.5px; }
.evo-pt-card .evo-panel-icon { width: 22px; height: 22px; border-radius: 7px; }
.evo-pt-card .evo-info-rows { gap: 3px; }
.evo-pt-card .evo-info-row span { font-size: 9.5px; }
.evo-pt-card .evo-info-row strong { font-size: 10px; line-height: 1.2; }
.evo-pt-card .evo-diagnosis { padding: 5px 6px; font-size: 9.5px; }
.evo-pt-card .evo-extra-grid { gap: 5px; }
.evo-pt-card .evo-extra-card { padding: 4px 7px; }
.evo-pt-card .evo-extra-card strong { margin-top: 1px; }
.evo-pt-card .evo-textarea-clinical { min-height: 44px; margin-bottom: 6px; padding: 7px 10px; }
.evo-pt-card .evo-history-box-compact { max-height: 76px; padding: 6px 10px; }
.evo-pt-card .evo-history-title { margin-bottom: 3px; }
.evo-pt-card .evo-nota { padding: 2px 0 6px; }
.evo-pt-card .evo-nota-texto { font-size: 11.5px; line-height: 1.4; }
.evo-pt-card .evo-detail-top .evo-detail-side .evo-panel-violet { flex: 1; }
.evo-pt-card .evo-detail-bottom .evo-detail-side .evo-panel-red { flex: 1; }
.evo-pt-card .evo-panel-clinical { display: flex; flex-direction: column; }
.evo-pt-card .evo-history-box-compact { flex: 1 1 auto; min-height: 76px; max-height: 190px; }
.evo-pt-card .evo-pt-footer { padding-top: 8px; }
.evo-pt-card .evo-detail-footer-btn { min-height: 34px; }
@media (max-width: 900px) {
  .evo-pt-card .evo-detail-top > .evo-detail-side { grid-column: 1 / -1; }
}
@media (max-width: 640px) {
  .evo-pt-card .evo-pt-head { flex-wrap: wrap; }
  .evo-pt-card .evo-pt-chips { flex-basis: 100%; margin: 4px 0 0; }
  .evo-pt-card .evo-pt-scroll { max-height: none; overflow: visible; }
  .evo-pt-card .evo-history-box-compact { max-height: 140px; }
}
@media (max-width: 640px) {
  .evo-card.evo-pt-card { padding: 14px 14px 12px; border-radius: 18px; }
  .evo-pt-head { gap: 10px; padding-right: 0; }
  .evo-avatar-lg { width: 44px; height: 44px; font-size: 14px; }
  .evo-pt-id h2 { font-size: 15px; }
  .evo-pt-scroll { max-height: none; min-height: 0; overflow: visible; padding-right: 0; }
  .evo-extra-grid { grid-template-columns: 1fr; }
  .evo-pt-footer { flex-direction: column; align-items: stretch; }
  .evo-pt-actions { flex-direction: column-reverse; align-items: stretch; }
  .evo-pt-footer .evo-detail-footer-btn { width: 100%; }
}
.evo-workspace-results .evo-login-prompt { align-self: center; width: min(480px, 100%); }
@media (max-width: 680px) {
  .evo-workspace { padding-bottom: 40px; }
  .evo-lookup-card-compact { grid-template-columns: 36px minmax(0, 1fr); grid-template-areas: "icon title" "input input" "btn btn"; }
}
@media (prefers-reduced-motion: reduce) {
  .evo-page { animation: none !important; }
  .evo-lookup-submit { transition: none; }
}

.evo-header-bar {
  width: 4px;
  height: 38px;
  border-radius: 4px;
  background: linear-gradient(180deg, var(--rf-primary, #0d2d6b), #2563eb);
}
.evo-title { color: #1e2d55; letter-spacing: -0.01em; }
.dark .evo-title { color: #e2e8f0; }
.evo-sub { color: #8a9ab5; }
.dark .evo-sub { color: #94a3b8; }

.evo-refresh {
  padding: 7px 13px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
}
.evo-refresh:hover { border-color: var(--rf-primary, #0d2d6b); color: var(--rf-primary, #0d2d6b); }
.dark .evo-refresh { background: #1e293b; border-color: #334155; color: #cbd5e1; }

.evo-search-card, .evo-card {
  background: #fff;
  border: 1px solid #e8edf5;
  box-shadow: 0 1px 3px rgba(13, 45, 107, 0.05);
}
.dark .evo-search-card, .dark .evo-card {
  background: #131a2a;
  border-color: #1f2b45;
  box-shadow: none;
}

.filter-label {
  display: block;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #8a9ab5;
  margin-bottom: 5px;
}
.dark .filter-label { color: #64748b; }

.evo-card-head {
  border-bottom: 1px solid #eef2f9;
  background: linear-gradient(90deg, rgba(13, 45, 107, 0.025), transparent 60%);
}
.dark .evo-card-head { border-color: #1f2b45; background: rgba(255,255,255,0.02); }
.evo-card {
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.evo-card:hover {
  transform: translateY(-2px);
  border-color: #cbdaf2;
  box-shadow: 0 12px 28px rgba(13, 45, 107, 0.09);
}
.dark .evo-card:hover { border-color: #334a70; box-shadow: 0 12px 28px rgba(0,0,0,.22); }
.evo-card-body { background: linear-gradient(180deg, #fff, #fcfdff); }
.dark .evo-card-body { background: linear-gradient(180deg, #131a2a, #111827); }

.evo-quick-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.evo-quick-fact {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 12px;
  min-width: 0;
  border-radius: 11px;
  background: #edf3fd;
  border: 1px solid #e0ebfa;
}
.dark .evo-quick-fact { background: rgba(29,44,78,.55); border-color: #263352; }
.evo-quick-fact-icon {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  flex-shrink: 0;
  color: #fff;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  box-shadow: 0 2px 6px rgba(37, 99, 235, .3);
}
.evo-quick-fact strong { color: #1e3a6e; font-size: 11.5px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dark .evo-quick-fact strong { color: #cbd5e1; }

.evo-detail-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 30px;
  padding: 0 10px;
  border: 1px solid #d9e5f6;
  border-radius: 9px;
  background: #fff;
  color: #16468e;
  font-size: 10px;
  font-weight: 800;
  cursor: pointer;
  transition: background .15s ease, border-color .15s ease, transform .15s ease;
}
.evo-detail-btn:hover { background: #eff6ff; border-color: #9fc0ef; transform: translateY(-1px); }
.dark .evo-detail-btn { background: #17243b; border-color: #2d4265; color: #93c5fd; }
.dark .evo-detail-btn:hover { background: #1e3a5f; border-color: #3b82f6; }

.evo-avatar {
  width: 40px;
  height: 40px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
  color: #fff;
  background: linear-gradient(135deg, var(--rf-primary, #0d2d6b), #2563eb);
  box-shadow: inset 0 0 0 1.5px rgba(255,255,255,0.3);
  flex-shrink: 0;
}

.evo-id-chip {
  font-size: 9.5px;
  font-weight: 800;
  letter-spacing: 0.04em;
  padding: 2px 7px;
  border-radius: 6px;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #dbeafe;
}
.dark .evo-id-chip { background: #1e3a5f; color: #93c5fd; border-color: #1e3a5f; }

.evo-urgencia-chip {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.05em;
  padding: 2px 7px;
  border-radius: 6px;
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
  animation: urgencia-pulse 1.6s ease-in-out infinite;
}
.dark .evo-urgencia-chip { background: #4c1d1d; color: #fca5a5; border-color: #7f1d1d; }
@keyframes urgencia-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.evo-estado-chip {
  font-size: 10px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
}
.evo-estado-chip.estado-pendiente { background: #fef3c7; color: #b45309; }
.evo-estado-chip.estado-pendiente_cama { background: #ffedd5; color: #c2410c; }
.evo-estado-chip.estado-en_espera { background: #dbeafe; color: #1d4ed8; }
.dark .evo-estado-chip.estado-pendiente { background: #422f0c; color: #fbbf24; }
.dark .evo-estado-chip.estado-pendiente_cama { background: #431407; color: #fdba74; }
.dark .evo-estado-chip.estado-en_espera { background: #1e3a5f; color: #60a5fa; }

.evo-dias-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
}
.dark .evo-dias-chip { background: #1e293b; color: #94a3b8; }

.evo-nota-label {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 11.5px;
  font-weight: 800;
  color: #1e3a6e;
  text-transform: capitalize;
}
.dark .evo-nota-label { color: #cbd5e1; }
.evo-nota-label-icon {
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border-radius: 6px;
  background: #dbeafe;
  color: #1d4ed8;
}
.dark .evo-nota-label-icon { background: #1e3a5f; color: #93c5fd; }

.evo-nota-hoy {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 700;
  color: #16a34a;
}
.dark .evo-nota-hoy { color: #4ade80; }

.evo-textarea {
  width: 100%;
  resize: vertical;
  min-height: 52px;
  padding: 9px 12px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  font-size: 12.5px;
  line-height: 1.5;
  color: #1e2d55;
  background: #fbfcfe;
  outline: none;
  transition: border-color .15s ease, box-shadow .15s ease;
}
.evo-textarea::placeholder { color: #a5b3c9; }
.evo-textarea:focus {
  border-color: var(--rf-primary, #0d2d6b);
  box-shadow: 0 0 0 3px rgba(13, 45, 107, 0.08);
}
.dark .evo-textarea {
  background: #0f172a;
  border-color: #334155;
  color: #e2e8f0;
}
.dark .evo-textarea::placeholder { color: #475569; }
.dark .evo-textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }

.evo-history-row {
  display: flex;
  align-items: stretch;
  gap: 10px;
}
.evo-history-box {
  flex: 1;
  min-width: 0;
  padding: 8px 12px;
  border-radius: 12px;
  border: 1px solid #e0ebfa;
  background: #edf3fd;
  max-height: 170px;
  overflow-y: auto;
}
.dark .evo-history-box { background: rgba(29,44,78,.55); border-color: #263352; }

.evo-save-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  align-self: center;
  padding: 12px 20px;
  border-radius: 12px;
  border: none;
  font-size: 12.5px;
  font-weight: 800;
  color: #fff;
  background: linear-gradient(135deg, #1d4ed8, #2563eb 55%, #3b82f6);
  cursor: pointer;
  flex-shrink: 0;
  transition: opacity .15s ease, transform .15s ease, box-shadow .15s ease;
  box-shadow: 0 6px 16px rgba(29, 78, 216, 0.35);
}
.evo-save-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(29, 78, 216, 0.42); }
.evo-save-btn:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }

.evo-nota {
  display: flex;
  gap: 10px;
  position: relative;
  padding: 5px 0 11px 0;
}
.evo-nota:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 5px;
  top: 17px;
  bottom: -2px;
  width: 1.5px;
  background: #c9dbf6;
}
.dark .evo-nota:not(:last-child)::before { background: #334a70; }

.evo-nota-dot {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  margin-top: 4px;
  flex-shrink: 0;
  background: linear-gradient(135deg, var(--rf-primary, #0d2d6b), #2563eb);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.evo-nota-fecha {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 10.5px;
  font-weight: 700;
  color: #64748b;
  margin-bottom: 3px;
}
.dark .evo-nota-fecha { color: #94a3b8; }

.evo-nota-texto {
  font-size: 12.5px;
  line-height: 1.55;
  color: #334155;
  white-space: pre-line;
}
.dark .evo-nota-texto { color: #cbd5e1; }

.evo-sin-notas {
  margin: 0;
  font-size: 11.5px;
  color: #7d90ae;
  font-style: italic;
}
.dark .evo-sin-notas { color: #64748b; }

/* Vacío / login */
.evo-login-prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 60px 20px;
  background: #fff;
  border: 1px dashed #dbe3ef;
  border-radius: 20px;
}
.dark .evo-login-prompt { background: #131a2a; border-color: #1f2b45; }

.evo-empty-icon {
  width: 60px;
  height: 60px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
  color: var(--rf-primary, #0d2d6b);
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
}
.dark .evo-empty-icon { background: #1e293b; color: #60a5fa; }

.evo-empty-title { font-size: 15px; font-weight: 800; color: #1e2d55; margin-bottom: 6px; }
.dark .evo-empty-title { color: #e2e8f0; }
.evo-empty-sub { font-size: 12px; color: #8a9ab5; max-width: 380px; line-height: 1.55; }
.dark .evo-empty-sub { color: #94a3b8; }

.evo-detail-dialog :deep(.el-dialog) {
  border: 1px solid rgba(226,232,240,.9);
  border-radius: 20px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 35, 75, .28);
  max-height: calc(100vh - 36px);
  display: flex;
  flex-direction: column;
}
.evo-detail-dialog :deep(.el-dialog__header) {
  margin: 0;
  padding: 11px 20px;
  border-bottom: 1px solid rgba(255,255,255,.14);
  background:
    radial-gradient(ellipse at 90% 0%, rgba(56,189,248,.3), transparent 35%),
    linear-gradient(108deg, #09275e 0%, #0c397f 58%, #0758ac 100%);
}
.evo-detail-dialog :deep(.el-dialog__body) { padding: 13px 20px 15px; max-height: calc(100vh - 145px); overflow-y: auto; background: #f5f8fc; }
.evo-detail-dialog :deep(.el-dialog__footer) { padding: 0 20px 16px; background: #fff; flex-shrink: 0; }
.dark .evo-detail-dialog :deep(.el-dialog) { border-color: #263352; background: #0f172a; }
.dark .evo-detail-dialog :deep(.el-dialog__header) { border-color: rgba(255,255,255,.1); background: linear-gradient(108deg, #071a3b, #0b2c60 65%, #0c4279); }
.dark .evo-detail-dialog :deep(.el-dialog__body) { background: #0b1220; color: #cbd5e1; }
.dark .evo-detail-dialog :deep(.el-dialog__footer) { background: #0f172a; }
.evo-modal-header { display: flex; align-items: center; gap: 18px; min-height: 48px; }
.evo-modal-brand { display: flex; align-items: center; gap: 9px; min-width: 185px; padding-right: 18px; border-right: 1px solid rgba(255,255,255,.22); }
.evo-modal-logo { display: block; width: 37px; height: 37px; padding: 3px; border-radius: 50%; background: #fff; object-fit: contain; box-shadow: 0 0 0 2px rgba(255,255,255,.25); }
.evo-modal-brand-copy { display: flex; flex-direction: column; gap: 2px; color: #fff; line-height: 1.1; }
.evo-modal-brand-copy strong { font-size: 13px; font-weight: 800; }
.evo-modal-brand-copy span { color: rgba(255,255,255,.7); font-size: 9px; }
.evo-modal-title-group { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
.evo-modal-title-icon { display: grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(255,255,255,.23); border-radius: 10px; background: rgba(255,255,255,.12); color: white; }
.evo-modal-title { margin: 0; color: #fff; font-size: 17px; font-weight: 850; }
.dark .evo-modal-title { color: #e2e8f0; }
.evo-modal-subtitle { margin: 3px 0 0; color: rgba(235,243,255,.78); font-size: 10.5px; }
.evo-modal-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.evo-modal-status { padding: 5px 11px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: 10px; font-weight: 800; }
.evo-modal-status.status-pendiente { background: #fef3c7; color: #b45309; }
.evo-modal-status.status-pendiente_cama { background: #ffedd5; color: #c2410c; }
.evo-modal-status.status-en_espera { background: #dbeafe; color: #1d4ed8; }
.dark .evo-modal-status { background: #164e63; color: #a5f3fc; }
.dark .evo-modal-status.status-pendiente { background: #422f0c; color: #fbbf24; }
.dark .evo-modal-status.status-pendiente_cama { background: #431407; color: #fdba74; }
.dark .evo-modal-status.status-en_espera { background: #1e3a5f; color: #93c5fd; }
.evo-detail-close {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 9px;
  color: #fff;
  background: rgba(255,255,255,.12);
  cursor: pointer;
}
.evo-detail-close:hover { background: rgba(255,255,255,.22); color: #fff; }
.dark .evo-detail-close { border-color: rgba(255,255,255,.2); background: rgba(255,255,255,.1); color: #e2e8f0; }
.evo-modal-body { display: flex; flex-direction: column; gap: 10px; }
.evo-detail-top { display: grid; grid-template-columns: 1fr 1.05fr .95fr; gap: 10px; align-items: stretch; }
.evo-detail-bottom { display: grid; grid-template-columns: 1.7fr .9fr; gap: 10px; align-items: stretch; }
.evo-detail-side { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.evo-panel { min-width: 0; padding: 11px 12px; border: 1px solid #e2e9f4; border-radius: 15px; background: #fff; box-shadow: 0 2px 7px rgba(20,48,88,.04); }
.dark .evo-panel { border-color: #263352; background: #121c2d; box-shadow: none; }
.evo-panel-blue { --panel: #2563eb; --panel-soft: #eff6ff; --panel-border: #dbeafe; }
.evo-panel-amber { --panel: #d97706; --panel-soft: #fffbeb; --panel-border: #fde68a; }
.evo-panel-violet { --panel: #7c3aed; --panel-soft: #f5f3ff; --panel-border: #ddd6fe; }
.evo-panel-slate { --panel: #475569; --panel-soft: #f1f5f9; --panel-border: #e2e8f0; }
.evo-panel-red { --panel: #e11d48; --panel-soft: #fff1f2; --panel-border: #fecdd3; }
.evo-panel-title { display: flex; align-items: center; gap: 8px; margin-bottom: 9px; color: var(--panel); font-size: 13px; font-weight: 850; }
.evo-panel-icon { display: grid; place-items: center; width: 27px; height: 27px; border-radius: 9px; background: var(--panel-soft); color: var(--panel); }
.dark .evo-panel-icon { background: color-mix(in srgb, var(--panel) 17%, #121c2d); }
.evo-info-rows { display: flex; flex-direction: column; gap: 7px; }
.evo-info-row { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; min-width: 0; }
.evo-info-row span { flex: 0 0 39%; color: #8494ad; font-size: 10px; font-weight: 650; }
.evo-info-row strong { min-width: 0; flex: 1; color: #24334d; font-size: 10.5px; font-weight: 750; line-height: 1.3; text-align: left; overflow-wrap: anywhere; }
.dark .evo-info-row strong { color: #d5deea; }
.evo-panel-violet .evo-diagnosis { background: #faf8ff; border-color: #ede9fe; }
.dark .evo-panel-violet .evo-diagnosis { background: #1b1730; border-color: #3b2c64; }
.evo-diagnosis-list { display: flex; flex-direction: column; gap: 6px; }
.evo-diagnosis { display: flex; align-items: flex-start; gap: 7px; padding: 7px; border: 1px solid #edf1f7; border-radius: 9px; color: #475569; font-size: 9px; line-height: 1.4; }
.dark .evo-diagnosis { border-color: #263352; color: #cbd5e1; }
.evo-diagnosis-code { flex-shrink: 0; padding: 2px 5px; border-radius: 5px; background: #ede9fe; color: #6d28d9; font-weight: 850; }
.dark .evo-diagnosis-code { color: #93c5fd; }
.dark .evo-diagnosis-code { background: #31245a; color: #c4b5fd; }
.evo-diagnosis-description { min-width: 0; }
.evo-principal-chip { flex-shrink: 0; margin-left: auto; padding: 2px 6px; border-radius: 999px; background: #eff6ff; color: #2563eb; font-size: 7px; font-weight: 800; }
.evo-detail-paragraph { margin: 0; padding: 9px; border-radius: 9px; background: #f8fafc; color: #64748b; font-size: 9px; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; }
.dark .evo-detail-paragraph { background: #0f172a; color: #cbd5e1; }
.evo-panel-clinical {
  --panel: #0754b5;
  --panel-soft: #eaf3ff;
  --panel-border: #c7ddff;
  padding: 12px 13px;
  background:
    radial-gradient(ellipse at 95% 0%, rgba(96,165,250,.1), transparent 30%),
    #fff;
}
.dark .evo-panel-clinical { background: linear-gradient(140deg, #12233d, #111b2c); }
.evo-clinical-note {
  min-height: 90px;
  max-height: 170px;
  margin: 0;
  padding: 13px 15px;
  overflow-y: auto;
  border: 1px solid #a9ccff;
  border-radius: 12px;
  background: linear-gradient(145deg, #fafdff, #f2f7ff);
  color: #253753;
  font-size: 12px;
  line-height: 1.6;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.dark .evo-clinical-note { border-color: #345985; background: #0d192b; color: #d9e7fa; }
.evo-clinical-alerts {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  margin-top: 10px;
  padding: 10px 12px;
  border: 1px solid #fde3b0;
  border-radius: 10px;
  background: linear-gradient(100deg, #fff7e6, #fff3d5);
  color: #85420c;
  font-size: 10.5px;
}
.evo-clinical-alerts > div + div { padding-left: 18px; border-left: 1px solid #e7bf78; }
.dark .evo-clinical-alerts { border-color: #62451c; background: #2c2416; color: #fdc979; }
.dark .evo-clinical-alerts > div + div { border-color: #725625; }
.evo-history-copy { max-height: 148px; overflow-y: auto; padding: 8px; border-radius: 9px; background: #f5f9ff; color: #53647d; font-size: 8px; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; }
.dark .evo-history-copy { background: #0d1728; color: #b6c3d7; }
.evo-extra-info { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; padding: 7px 8px; border-radius: 9px; background: #fff7ed; color: #9a3412; font-size: 8px; line-height: 1.35; }
.dark .evo-extra-info { background: #2a1b16; color: #fdba74; }
.evo-tracking-row { display: flex; gap: 8px; position: relative; padding: 2px 0 10px; }
.evo-tracking-row + .evo-tracking-row { padding-bottom: 4px; }
.evo-tracking-row:first-of-type::after { content: ''; position: absolute; left: 8px; top: 20px; bottom: -1px; width: 1px; background: #dbe5f1; }
.dark .evo-tracking-row:first-of-type::after { background: #334155; }
.evo-tracking-dot { z-index: 1; display: grid; place-items: center; width: 17px; height: 17px; flex-shrink: 0; border-radius: 50%; }
.evo-tracking-dot.done { background: #dbeafe; color: #2563eb; }
.evo-tracking-dot.current { background: #fef3c7; color: #d97706; }
.evo-tracking-row strong, .evo-tracking-row small { display: block; }
.evo-tracking-row strong { color: #334155; font-size: 9px; font-weight: 800; }
.evo-tracking-row small { margin-top: 2px; color: #94a3b8; font-size: 8px; }
.dark .evo-tracking-row strong { color: #dbe4f0; }
.evo-arrival-chip { margin-top: 7px; padding: 6px 7px; border-radius: 7px; background: #ecfdf5; color: #047857; font-size: 8px; font-weight: 750; }
.dark .evo-arrival-chip { background: #102b28; color: #6ee7b7; }
.evo-count { margin-left: auto; min-width: 17px; padding: 2px 5px; border-radius: 999px; background: var(--panel-soft); text-align: center; font-size: 8px; }
.evo-attachments { display: flex; flex-direction: column; gap: 5px; }
.evo-attachment { display: flex; align-items: center; gap: 7px; min-width: 0; padding: 5px 6px; border: 1px solid #f1f5f9; border-radius: 8px; }
.dark .evo-attachment { border-color: #263352; }
.evo-attachment-type { display: grid; place-items: center; min-width: 24px; height: 23px; padding: 0 3px; border-radius: 6px; background: #ffe4e6; color: #e11d48; font-size: 6px; font-weight: 900; }
.evo-attachment-name { min-width: 0; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #475569; font-size: 8px; font-weight: 700; }
.evo-attachment small { flex-shrink: 0; color: #94a3b8; font-size: 7px; }
.dark .evo-attachment-name { color: #cbd5e1; }
.evo-no-attachments { margin: 0; color: #94a3b8; font-size: 8px; font-style: italic; }
.evo-modal-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.evo-modal-footer > span { display: inline-flex; align-items: center; gap: 6px; color: #657795; font-size: 10px; }
.evo-modal-footer-actions { display: flex; align-items: center; gap: 10px; }
.evo-detail-footer-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 15px; border: 0; border-radius: 10px; background: linear-gradient(135deg, #0870d1, #08489f); color: #fff; font-size: 11px; font-weight: 850; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.2); }
.evo-detail-footer-btn-light { border: 1px solid #8ab7f7; background: #fff; color: #0754b5; box-shadow: none; }
.evo-detail-footer-btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
.dark .evo-detail-footer-btn-light { border-color: #345985; background: #13233a; color: #aad0ff; }

@media (max-width: 900px) {
  .evo-detail-top { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .evo-panel-violet { grid-column: 1 / -1; }
  .evo-detail-bottom { grid-template-columns: 1.3fr 1fr; }
  .evo-modal-header { gap: 11px; }
  .evo-modal-brand { min-width: 155px; padding-right: 12px; }
}

.skeleton-circle {
  width: 40px;
  height: 40px;
  border-radius: 11px;
  flex-shrink: 0;
}
.skeleton-bar, .skeleton-circle {
  background: linear-gradient(90deg, #eef2f9 25%, #e2e8f0 50%, #eef2f9 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.4s ease-in-out infinite;
  border-radius: 6px;
}
.dark .skeleton-bar, .dark .skeleton-circle {
  background: linear-gradient(90deg, #1e293b 25%, #263352 50%, #1e293b 75%);
  background-size: 200% 100%;
}
@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (max-width: 640px) {
  .evo-page { padding: 8px !important; }
  .evo-workspace { gap: 10px; padding-bottom: 18px; }
  .evo-lookup-card { padding: 18px 16px 20px; border-radius: 18px; }
  .evo-lookup-title { font-size: 19px; }
  .evo-lookup-subtitle { margin-bottom: 14px; }
  .evo-lookup-card-compact {
    width: 100%;
    max-width: 100%;
    grid-template-columns: 32px minmax(0, 1fr);
    gap: 8px;
    padding: 10px;
  }
  .evo-lookup-card-compact .evo-lookup-title { font-size: 13px; }
  .evo-lookup-card-compact :deep(.el-input),
  .evo-lookup-card-compact .evo-lookup-submit { grid-column: 1 / -1; }
  .evo-results-list, .evo-card { min-width: 0; width: 100%; }
  .evo-card-head { padding: 10px !important; gap: 8px; }
  .evo-card-head > div:nth-child(2) { min-width: 0; }
  .evo-card-body { padding: 10px !important; }
  .evo-textarea { width: 100%; min-height: 78px; font-size: 12px; }
  .evo-history-row { gap: 8px; }
  .evo-history-box { width: 100%; }
  .evo-save-btn { min-height: 40px; }
  .evo-quick-facts { grid-template-columns: 1fr; }
  .evo-history-row { flex-direction: column; }
  .evo-history-box { max-height: none; }
  .evo-save-btn { align-self: stretch; }
  .evo-card-head { align-items: flex-start; }
  .evo-card-head > div:nth-child(3) { width: 100%; justify-content: flex-end; flex-wrap: wrap; }
  .evo-detail-dialog :deep(.el-dialog__header) { padding: 15px; }
  .evo-detail-dialog :deep(.el-dialog__body) { padding: 12px 14px 6px; }
  .evo-detail-dialog :deep(.el-dialog__footer) { padding: 0 14px 14px; }
  .evo-modal-header { align-items: flex-start; flex-wrap: wrap; }
  .evo-modal-brand { min-width: 0; padding-right: 10px; }
  .evo-modal-title-group { flex: 1 1 210px; }
  .evo-modal-actions { width: 100%; justify-content: flex-end; }
  .evo-detail-top, .evo-detail-bottom { grid-template-columns: 1fr; }
  .evo-panel-violet { grid-column: auto; }
  .evo-modal-footer { align-items: stretch; flex-direction: column; }
  .evo-modal-footer-actions { justify-content: flex-end; }
}

@media (max-width: 420px) {
  .evo-modal-header { flex-direction: column; }
  .evo-modal-actions { width: 100%; justify-content: flex-start; }
  .evo-modal-actions .evo-detail-close { margin-left: auto; }
}
</style>
