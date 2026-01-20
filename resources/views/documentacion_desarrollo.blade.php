<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-0">
            {{ __('Documentación de Desarrollo') }}
        </h2>
    </x-slot>

    <div class="container">
        <div class="row mt-3">
            <div class="col-12 mb-3">
                <button id="descargar_gantt" class="btn btn-success float-end ml-3">
                    <i class="fa-solid fa-chart-gantt"></i> Descargar Gantt
                </button>
                <button id="presentacion_ejecutiva" class="btn btn-primary float-end">
                    <i class="fa-solid fa-person-chalkboard"></i> Descargar Presentación
                </button>
            </div>
            <div class="accordion" id="acordionDiagramas">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#acordionCasosUso" aria-expanded="true" aria-controls="acordionCasosUso">
                            Diagramas de Casos de Uso
                        </button>
                    </h2>
                    <div id="acordionCasosUso" class="accordion-collapse collapse show" data-bs-parent="#acordionDiagramas">
                        <div class="accordion-body">
                            <div class="row">
                                @foreach ($roles as $rol)
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <span class="card-text">
                                                Diagrama de casos de uso - {{ $rol['name'] }}
                                            </span>
                                            <a href="{{ $rol['url_diagrama'] }}" role="button" class="btn btn-primary float-end" target="_blank">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        </div>
                                        <div class="card-body">
                                            <img src="{{ $rol['imagen_diagrama'] }}" class="card-img-top">
                                        </div>
                                    </div>  
                                </div>  
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#acordionGantt" aria-expanded="true" aria-controls="acordionGantt">
                            Diagrama de Gantt
                        </button>
                    </h2>
                    <div id="acordionGantt" class="accordion-collapse collapse show" data-bs-parent="#acordionDiagramas">
                        <div class="accordion-body overflow-x-auto">
                            <div id="diagrama_gantt" class="overflow-x-hidden"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
    @push('scripts')
        <script type="module" nonce="{{ csp_nonce() }}">
            $(document).ready(function(){
                let estilos = window.getComputedStyle(document.body);
                let fuente_titulo = 'Noto Sans Black';
                let fuente_texto = 'Noto Sans';
                let perfiles = @json($nombres_perfiles);
                let porcentaje_perfiles = @json($porcentaje_perfiles);
                
                let total_tareas_completadas = @json($total_tareas_completadas);
                let ultima_tarea = @json($ultima_tarea);

                let modulos = @json($modulos);

                let indice = 1;
                let colores_tareas = [];
                while(estilos.getPropertyValue('--colorInstitucional' + indice) != '') {
                    if (!window.color_fondo_claro(estilos.getPropertyValue('--colorInstitucional' + indice))) { //Solo pone fondos compatibles con el fondo negro
                        colores_tareas.push('bg-institucional' + indice);
                    }
                    indice++;
                }
                let numero_colores = colores_tareas.length;

                window.gantt.plugins({
                    export_api: true
                });

                window.gantt.i18n.setLocale('es'); //Cambiando el idioma del Gantt a Español
                window.gantt.config.sort = true; //Habilitando el ordenamiento de las columnas
                window.gantt.config.autosize = 'x'; // solo se ajusta en el eje X (horizontal)
                window.gantt.config.autofit = false; // no encoge filas para caber en el div
                window.gantt.config.row_height = 40;
                window.gantt.config.autosize = false; // desactiva ajuste automático
                window.gantt.config.fit_tasks = false; // no encoge ancho a las tareas
                const tareas_gantt = @json($tareas_gantt);

                window.gantt.config.date_format = '%Y-%m-%d %H:%i:%s';

                window.gantt.config.columns = [
                    {name: 'text', label: 'Nombre de la Tarea', tree: true, width: '400' , align: 'left' },
                    {name: 'start_date', label: 'Inicio', align: 'center', width: '100' },
                    {name: 'end_date', label: 'Fin', align: 'center', width: '100' },
                    {name: 'status_label', label: 'Estado', align: 'center', width: '100' },
                    {name: "assigned", label: "Responsable(s)", template: function (tarea) { return tarea.assigned; }},
                ];

                window.gantt.init('diagrama_gantt');
                window.gantt.parse({ data: tareas_gantt });
                window.gantt.config.autosize = 'xy'; 

                window.gantt.sort('start_date', false); //Ordenando por fecha de inicio de la tarea

                window.gantt.templates.task_class = function (fecha_inicio, fecha_fin, tarea) { //Agregando colores a las tareas
                    if (tarea.$level == 2) {
                        return tarea.status_label == 'Completada' ? 'bg-success' : (tarea.status_label == 'En curso' ? '.bg-warning' : 'bg-danger');
                    } else {
                        return 'text-white ' + colores_tareas[tarea.$local_index % numero_colores];
                    }
                }

                $('#presentacion_ejecutiva').click(function(){
                    const presentacion = new PptxGenJS();
                    presentacion.layout = "LAYOUT_WIDE";
                    const slide = presentacion.addSlide();

                    slide.addImage({ //Imagen superior
                        data: 'data:image/jpeg;base64,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',
                        x: '72%',
                        y: '3%',
                        w: '24%',
                        h: '6%'
                    });

                    slide.addText('{{ config("app.name") }}', { //Nombre del sistema
                        x: '3%',
                        y: '3%',
                        w: '66%',
                        h: '8%',
                        valign: 'top',
                        fontFace: fuente_titulo,
                        fontSize: 28,
                        color: estilos.getPropertyValue('--colorInstitucional1')
                    });

                    slide.addText('Resumen ejecutivo', { //Resumen ejecutivo
                        x: '3%',
                        y: '14%',
                        w: '30%',
                        h: '21%',
                        valign: 'top',
                        fontFace: fuente_texto,
                        fontSize: 16,
                        color: estilos.getPropertyValue('--colorInstitucional2')
                    });

                    slide.addText([ //Porcentaje de avance del sistema
                        { text: 'Tareas Completadas', options: { fontSize: 12, color: estilos.getPropertyValue('--colorInstitucional3'), breakLine: true } },
                        { text: total_tareas_completadas, options: { fontSize: 18, color: estilos.getPropertyValue('--colorInstitucional3') } }
                    ], {
                        x: '3%',
                        y: '38%',
                        w: '10%',
                        h: '9%',
                        align: 'center',
                        fontFace: fuente_texto,
                        fill: { color: estilos.getPropertyValue('--colorInstitucional1') }
                    });

                    slide.addText([
                        { text: 'Ultima actividad', options: { breakLine: true } },
                        { text: ultima_tarea.task_name, options: { fontSize: 10, breakLine: true } },
                        { text: ultima_tarea.name, options: { fontSize: 10 } }
                    ], { //Ultima actividad realizada
                        x: '15%',
                        y: '38%',
                        w: '17%',
                        h: '9%',
                        valign: 'top',
                        fontFace: fuente_texto,
                        fontSize: 11,
                        color: estilos.getPropertyValue('--colorInstitucional2')
                    });

                    slide.addChart( //Se genera la gráfica con los progresos por los módulos principales
                        presentacion.charts.BAR,
                        [
                            {
                                name: 'Proceso de desarrollo',
                                labels: perfiles,
                                values: porcentaje_perfiles,
                            },
                        ],
                        {
                            x: 0.4,
                            y: 3.8,
                            w: '28%',
                            h: '47%',
                            barDir: "bar",
                            chartColors: [estilos.getPropertyValue('--colorInstitucional4').replace('#', ''), estilos.getPropertyValue('--colorInstitucional4').replace('#', ''), estilos.getPropertyValue('--colorInstitucional4').replace('#', '')],
                            catAxisLabelColor: estilos.getPropertyValue('--colorInstitucional5'),
                            valAxisMaxVal: 100,
                            valAxisMinVal: 0,
                            valAxisHidden: false,
                            showTitle: true,
                            showPercent: true,
                            title: 'Proceso de desarrollo',
                            titleColor: estilos.getPropertyValue('--colorInstitucional2'),
                            titleFontSize: 14,
                        }
                    );

                    slide.addTable(
                        modulos, { 
                            x: '35%',
                            y: '14%',
                            w: '62%',
                            h: '66%',
                            align: 'left', 
                            fontFace: fuente_texto,
                            fontSize: 8,
                            border: {
                                type: 'solid',
                                pt: 1,
                                color: '#000000'
                            }
                        });

                    let tamanio_titulos = 62 / perfiles.length; //Generando el cintillo de módulos
                    for (let indice = 0; indice < perfiles.length && tamanio_titulos > 1; indice++) {
                        let color = (indice % 11) + 1;
                        slide.addText(perfiles[indice], {
                            shape: presentacion.ShapeType.rect,
                            x: (35 + (indice * tamanio_titulos)) + '%', 
                            y: '82%', 
                            w: (tamanio_titulos - 1) + '%', 
                            h: '15%',
                            color: window.color_fondo_claro(estilos.getPropertyValue('--colorInstitucional' + color)) ? '#FFFFFF' : '#000000',
                            fontSize: 8,
                            fill: { color: estilos.getPropertyValue('--colorInstitucional' + color) }, 
                            line: { type: 'none' }
                        });
                    }

                    presentacion.writeFile({fileName: 'Presentación {{ config("app.name") }}'});
                });
                
                $('#descargar_gantt').click(function(){
                    window.gantt.exportToExcel({
                        name: 'Gantt {{ config("app.name") }}.xlsx',
                        visual: 'base-colors',
                        cellColors: true
                    })
                });
            });
        </script>
    @endpush
</x-app-layout>
