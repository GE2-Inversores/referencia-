<template>
  <button
    class="w-8 h-8 rounded flex items-center justify-center transition-colors relative border-none cursor-pointer bg-transparent hover:bg-white/10 text-[var(--blue-300)] hover:text-white"
    title="Tiempo de llegada de pacientes aceptados"
    @click="abrir"
  >
    <TimerIcon class="w-4.5 h-4.5" />
    <span
      v-if="items.length > 0"
      class="absolute top-[5px] right-[5px] min-w-[16px] h-[16px] px-1 rounded-full bg-amber-500 text-[10px] font-bold text-white flex items-center justify-center border border-[var(--blue-800)]"
    >
      {{ items.length > 9 ? '9+' : items.length }}
    </span>
  </button>

  <el-dialog v-model="visible" width="560px" align-center append-to-body class="tiempo-dialog">
    <template #header>
      <div class="flex items-center gap-2.5">
        <span class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
          <TimerIcon class="w-4.5 h-4.5" />
        </span>
        <div class="leading-tight">
          <p class="text-[15px] font-bold text-gray-800">Tiempo de llegada</p>
          <p class="text-[11px] text-gray-400 font-medium">{{ items.length }} aceptado{{ items.length === 1 ? '' : 's' }} esperando ingreso</p>
        </div>
      </div>
    </template>

    <el-input
      v-model="busqueda"
      placeholder="Buscar por nombre o documento…"
      clearable
      size="large"
      class="mb-4"
    >
      <template #prefix>
        <SearchIcon class="w-4 h-4 text-gray-400" />
      </template>
    </el-input>

    <div v-loading="cargando" class="max-h-[440px] overflow-y-auto -mx-1 px-1">
      <div v-if="!cargando && filtrados.length === 0" class="py-12 text-center text-gray-400 text-sm">
        <TimerOffIcon class="w-9 h-9 mx-auto mb-2.5 text-gray-300" />
        {{ items.length === 0 ? 'No hay pacientes en espera' : 'Sin resultados para esa búsqueda' }}
      </div>

      <div
        v-for="s in filtrados"
        :key="s.id"
        class="flex items-center gap-4 p-4 mb-2.5 rounded-xl hover:bg-amber-50/60 hover:border-amber-200 transition-colors cursor-pointer border border-gray-100 bg-white"
        @click="irASolicitud(s.id)"
      >
        <div class="shrink-0">
          <div class="w-11 h-11 rounded-full flex items-center justify-center bg-amber-100 text-amber-600">
            <TimerIcon class="w-5 h-5" />
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <p class="text-[14px] font-bold text-gray-800 leading-tight capitalize truncate">{{ s.paciente.toLowerCase() }}</p>
          <p class="text-[12px] text-gray-500 mt-1 truncate">
            <span class="font-semibold text-gray-600">Aceptado:</span> {{ fechaAceptado(s.aceptado_en) }}
          </p>
          <p class="text-[12px] text-gray-500 mt-0.5 truncate">
            <span class="font-semibold text-gray-600">EPS:</span> {{ s.eps || 'Sin EPS' }}
            <span class="text-gray-300 mx-1">·</span> {{ s.documento || 'Sin documento' }}
          </p>
        </div>

        <div class="shrink-0 text-right">
          <p v-if="s.tiempo_llegada_horas" class="text-[15px] font-extrabold tabular-nums leading-tight" :class="tiempoVencido(s) ? 'text-red-500' : 'text-emerald-600'">
            {{ cuentaRegresiva(s) }}
          </p>
          <p v-else class="text-[15px] font-extrabold tabular-nums text-amber-500 leading-tight">{{ esperando(s.aceptado_en) }}</p>
          <p class="text-[9.5px] font-semibold text-gray-400 uppercase tracking-wide mt-0.5">
            {{ s.tiempo_llegada_horas ? (tiempoVencido(s) ? 'tiempo vencido' : `de ${s.tiempo_llegada_horas} h`) : 'en espera' }}
          </p>
        </div>
      </div>
    </div>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { usePolling } from '@/lib/usePolling';
import http from '@/plugins/axios';
import { Search as SearchIcon, Timer as TimerIcon, TimerOff as TimerOffIcon } from '@lucide/vue';

interface EnEspera {
  id: number;
  paciente: string;
  documento: string | null;
  eps: string | null;
  especialidad: string | null;
  clinica: string | null;
  codigo: string | null;
  aceptado_en: string | null;
  tiempo_llegada_horas: number | null;
  tiempo_llegada_inicia_en: string | null;
}

const items = ref<EnEspera[]>([]);
const cargando = ref(false);
const visible = ref(false);
const busqueda = ref('');
const router = useRouter();
const ahora = ref(Date.now());
let tick: ReturnType<typeof setInterval> | null = null;

const filtrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase();
  if (!q) return items.value;

  return items.value.filter((s) =>
    s.paciente.toLowerCase().includes(q) || (s.documento ?? '').toLowerCase().includes(q),
  );
});

async function cargar(): Promise<void> {
  cargando.value = true;
  try {
    const { data } = await http.get('/api/solicitudes-referencia/en-espera-tiempo');
    items.value = data.data ?? [];
  } catch {
    // Sin permiso o error de red: el botón simplemente muestra vacío.
  } finally {
    cargando.value = false;
  }
}

function abrir(): void {
  visible.value = true;
  busqueda.value = '';
  void cargar();
}

function fechaAceptado(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('es-CO', { day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit', hour12: true });
}

function esperando(iso: string | null): string {
  if (!iso) return '—';
  const min = Math.max(0, Math.floor((ahora.value - new Date(iso).getTime()) / 60000));
  if (min < 60) return `${min} min`;
  const h = Math.floor(min / 60);
  if (h < 24) return `${h} h ${min % 60} min`;
  return `${Math.floor(h / 24)} d ${h % 24} h`;
}

function tiempoInicio(s: EnEspera): number | null {
  const base = s.tiempo_llegada_inicia_en || s.aceptado_en;
  if (!base) return null;
  return new Date(base).getTime();
}

function tiempoRestanteSegundos(s: EnEspera): number {
  const inicio = tiempoInicio(s);
  const horas = Number(s.tiempo_llegada_horas ?? 0);
  if (!inicio || !horas) return 0;
  return Math.max(0, Math.floor((inicio + horas * 3_600_000 - ahora.value) / 1000));
}

function tiempoVencido(s: EnEspera): boolean {
  return tiempoRestanteSegundos(s) === 0;
}

function cuentaRegresiva(s: EnEspera): string {
  const total = tiempoRestanteSegundos(s);
  const h = Math.floor(total / 3600);
  const m = Math.floor((total % 3600) / 60);
  const seg = total % 60;
  return `${h}:${String(m).padStart(2, '0')}:${String(seg).padStart(2, '0')}`;
}

function irASolicitud(id: number): void {
  visible.value = false;
  void router.push(`/solicitudes-referencia?resaltar=${id}`);
}

usePolling(cargar, 60_000);

onMounted(() => {
  void cargar();
  tick = setInterval(() => { ahora.value = Date.now(); }, 1_000);
});

onUnmounted(() => {
  if (tick) clearInterval(tick);
});
</script>
