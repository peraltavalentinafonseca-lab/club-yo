document.addEventListener('DOMContentLoaded', () => {
    cargarMisPrestamos();
});

function calcularCuotaEstimada(valor, tasaMensual, cuotas) {
    const monto = Number(valor) || 0;
    const tasa = (Number(tasaMensual) || 0) / 100;
    const n = parseInt(cuotas, 10) || 1;

    if (monto <= 0 || n <= 0) return 0;
    if (tasa === 0) return monto / n;

    return (monto * tasa) / (1 - Math.pow(1 + tasa, -n));
}

async function cargarMisPrestamos() {
    const contenedor = document.getElementById('listaMisPrestamos') || document.getElementById('tablaMisPrestamos');
    if (!contenedor) return;

    try {
        const res = await fetch('../Modelos/misPrestamos.php', {
            credentials: 'same-origin'
        });

        const texto = await res.text();
        let data;
        try {
            data = JSON.parse(texto);
        } catch {
            throw new Error(`Respuesta no válida del servidor (${res.status}): ${texto.slice(0, 120)}`);
        }

        if (!res.ok || !data.success) {
            contenedor.innerHTML = `
                <div class="alert alert-warning" role="alert">
                    ${data.message || 'No fue posible cargar tus préstamos.'}
                </div>`;
            return;
        }

        const prestamos = data.prestamos || [];
        if (prestamos.length === 0) {
            contenedor.innerHTML = `
                <div class="alert alert-info" role="alert">
                    No tienes préstamos registrados actualmente.
                </div>`;
            return;
        }

        const filas = prestamos.map(p => {
            const cuotaEstimada = calcularCuotaEstimada(p.valorPrestamo, p.tasaInteres, p.numeroCuotas);
            return `
                <tr>
                    <td>${p.idPrestamo}</td>
                    <td>$${Number(p.valorPrestamo).toLocaleString('es-CO')}</td>
                    <td>${p.tasaInteres}%</td>
                    <td>${p.numeroCuotas}</td>
                    <td>${p.fechaPrestamo}</td>
                    <td>$${Math.round(cuotaEstimada).toLocaleString('es-CO')}</td>
                </tr>`;
        }).join('');

        contenedor.innerHTML = `
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Valor acordado</th>
                            <th>Tasa (%)</th>
                            <th>Cuotas</th>
                            <th>Fecha</th>
                            <th>Cuota aprox.</th>
                        </tr>
                    </thead>
                    <tbody>${filas}</tbody>
                </table>
            </div>`;
    } catch (error) {
        console.error('Error al cargar mis préstamos:', error);
        contenedor.innerHTML = `
            <div class="alert alert-danger" role="alert">
                ${error.message || 'Ocurrió un error al consultar tus préstamos.'}
            </div>`;
    }
}