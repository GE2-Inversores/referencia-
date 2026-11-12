import http from '@/plugins/axios';

/**
 * Descarga un libro de Excel (.xlsx) con las solicitudes indicadas. El archivo
 * se arma en el servidor para que salga como Excel real (no CSV).
 */
export async function exportarSolicitudesExcel(ids: number[], nombre: string): Promise<void> {
  const { data } = await http.post(
    '/api/solicitudes-referencia/exportar',
    { ids, nombre },
    { responseType: 'blob' },
  );

  const url = URL.createObjectURL(data as Blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${nombre}_${new Date().toISOString().slice(0, 10)}.xlsx`;
  a.click();
  URL.revokeObjectURL(url);
}
