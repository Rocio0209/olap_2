<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DocumentacionDesarrolloController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->hasRole('Administrador General') || env('APP_ENV') != 'local') {
            abort(403);
        }

        $roles = Role::with('permissions:id,group,name,description')->where('name', '!=', 'Administrador General')->get()->toArray(); //Obteniendo los roles excepto el de Administrador General

        foreach ($roles as $indice => $rol) {
            $contenido_diagrama_casos_uso = '@startuml'.PHP_EOL.'left to right direction'.PHP_EOL;
            $contenido_diagrama_casos_uso .= 'actor "'.$rol['name'].'" as '.$rol['id'].'r'.PHP_EOL; 

            foreach (collect($rol['permissions'])->groupBy('group') as $nombre_grupo => $grupo) { //Generando las acciones
                $contenido_diagrama_casos_uso .= 'package "'.$nombre_grupo.'" {'.PHP_EOL;
                foreach ($grupo as $permiso) {
                    $contenido_diagrama_casos_uso .= '  usecase "'.$permiso['description'].'" as '.$permiso['id'].'p'.PHP_EOL;
                }
                $contenido_diagrama_casos_uso .= '}'.PHP_EOL;
            }

            foreach ($rol['permissions'] as $permiso) { //Para cada uno de los roles asignado los permisos
                $contenido_diagrama_casos_uso .= $rol['id'].'r -->'.$permiso['id'].'p'.PHP_EOL; 
            }
            $contenido_diagrama_casos_uso .= '@enduml';

            $diagrama_casos_uso_codificado = PlantUML::codificar($contenido_diagrama_casos_uso);

            $solicitud = Http::get('https://www.plantuml.com/plantuml/svg/'.$diagrama_casos_uso_codificado); //Enviando la solicitud a PlantUML codificado el texto como Hexadecimal

            $roles[$indice]['url_diagrama'] = 'https://www.plantuml.com/plantuml/uml/'.$diagrama_casos_uso_codificado;
            $roles[$indice]['imagen_diagrama'] = $solicitud->failed() ? '' : 'data:image/svg+xml;base64,'.base64_encode((string)$solicitud->getBody()); //Conviertiendo el SVG en base 64
        }

        $clickup_api_token = env('CLICKUP_API_TOKEN');
        $clickup_folder = env('CLICKUP_FOLDER_ID');

        $solicitud_listas_clickup = Http::withHeaders([
            'Authorization' => $clickup_api_token,
        ])->get('https://api.clickup.com/api/v2/folder/'.$clickup_folder.'/list'); //Haciendo la peticion a Clickup para obtener todas nuestras listas de trabajo

        $listas_clickup = collect($solicitud_listas_clickup->json()['lists'])->pluck('id', 'name'); //Obteniendo los IDS junto con los nombres de cada una de las listas

        $cambiar_nombres_tareas = null;
        $nombres_tareas_ingles = ['completado' => 'complete', 'en curso' => 'in progress', 'pendiente' => 'to do']; //Dando soporte al nombre de las tareas en otros idiomas

        $todas_tareas_clickup = collect([]);
        foreach($listas_clickup as $nombre_lista => $lista_clickup) {
            $solicitud_tareas_lista = Http::withHeaders([
                'Authorization' => $clickup_api_token,
            ])->get('https://api.clickup.com/api/v2/list/'.$lista_clickup.'/task?subtasks=true&include_closed=true&order_by=due_date&reverse=true'); //Consultando las tareas y subtareas de cada una de las listas
            
            $fecha_inicial = null;
            $fecha_termino = null;
            if (isset($solicitud_tareas_lista->json()['tasks'])) { //Si obtuvimos las tareas entonces empezamos a procesarlas
                $tareas_clickup = collect($solicitud_tareas_lista->json()['tasks']);
                if ($cambiar_nombres_tareas !== false) { //Se forza el cambio de los nombres de las tareas/Normalizando el nombre de la tarea
                    if ($cambiar_nombres_tareas || !Str::of($tareas_clickup->whereNotNull('status.status')->first()['status']['status'])->lower()->contains(['complete', 'in progress', 'to do'])) { //
                        $cambiar_nombres_tareas = true;
                        $tareas_clickup = $tareas_clickup->map(function($tarea) use($nombres_tareas_ingles) { //Cambiando el nombre de los estado de las tareas
                            if ($tarea['status']['status']) { //
                                $tarea['status']['status'] = $nombres_tareas_ingles[Str::lower($tarea['status']['status'])];
                            }
                            return $tarea;
                        });
                    } else { //El nombre de las tareas ya esta normalizado
                       $cambiar_nombres_tareas = false;
                    }
                }
                $nombres_tareas_por_id = $tareas_clickup->pluck('name', 'id'); //Vinculando el ID con el nombre de la tarea
                $subtareas = $tareas_clickup->whereNotNull('parent'); //Contando todas las subtareas
                $estados_subtareas = $subtareas->countBy(function ($subtarea) { //Contando cada uno de los estados de las tareas
                    return Str::lower($subtarea['status']['status'] ?? 'not defined');
                });
                $tareas_ordenadas = $tareas_clickup->map(function($tarea) use($tareas_clickup, $lista_clickup, $nombres_tareas_por_id, $nombre_lista, &$fecha_inicial, &$fecha_termino){ //Para las tareas calculamos sus fechas de inicio y les ponemos como padre el ID de la lista
                    if ($tarea['parent'] === null) {
                        $tarea['type'] = 'modulo';
                        $tarea['parent'] = $lista_clickup;
                        $tarea['list_name'] = $nombre_lista;
                        $tareas_categoria = $tareas_clickup->where('parent', $tarea['id']); //Buscando todas las subtareas relacionada a esta tarea
                        $estados_tareas_categoria = $tareas_categoria->countBy(function ($subtarea) { //Contando cada uno de los estados de las tareas
                            return Str::lower($subtarea['status']['status'] ?? 'not defined');
                        });
                        $tarea['status'] = ['status' => $tareas_categoria->count() == $estados_tareas_categoria->get('complete') ? 'complete' : ($estados_tareas_categoria->get('in progress') > 0 ? 'in progress' : 'to do' )];
                        $tarea['start_date'] = $tareas_categoria->count() == 0 ? ($tarea['start_date'] ?? $tarea['date_created']) : ($tareas_categoria->min('start_date') ?? $tareas_categoria->min('date_created'));
                        $tarea['due_date'] = $tareas_categoria->count() == 0 ? $tarea['due_date'] : $tareas_categoria->max('due_date');
                        if (($fecha_inicial === null && $tarea['start_date'] !== null) || ($tarea['start_date'] !== null && $fecha_inicial > $tarea['start_date'])) { //Encontrando la fecha inicial para la tarea inicial
                            $fecha_inicial = $tarea['start_date'];
                        }
                        if (($fecha_termino === null && $tarea['due_date'] !== null) || ($tarea['due_date'] !== null && $fecha_termino < $tarea['due_date'])) { //Encontrando la fecha de termino de la tarea principal
                            $fecha_termino = $tarea['due_date'];
                        }
                    } else {
                        $tarea['type'] = 'tarea';
                        $tarea['task_name'] = $nombres_tareas_por_id->get($tarea['parent']) ?? '';
                        $tarea['list_name'] = $nombre_lista;
                        $tarea['status']['status'] ??= 'not defined';
                        $tarea['start_date'] ??= $tarea['date_created'];
                        $tarea['assigned'] = collect($tarea['assignees'])->pluck('username')->join(', ');
                    }
                    if ($tarea['start_date'] !== null && $tarea['due_date'] !== null && $tarea['start_date'] > $tarea['due_date']) { //La fecha de inicio no puede ser mayor a la de termino
                        $tarea['start_date'] = $tarea['due_date'];
                    }
                    return $tarea;
                });

                $total_tareas = $subtareas->count();
                $total_tareas_completadas = $estados_subtareas->get('complete');
                $tareas_ordenadas->push([ //Agregando la lista principal al Gantt
                    'id' => $lista_clickup,
                    'name' => $nombre_lista,
                    'start_date' => $fecha_inicial,
                    'due_date' => $fecha_termino,
                    'parent' => 0,
                    'type' => 'lista',
                    'status' => ['status' => $total_tareas == $total_tareas_completadas ? 'complete' : ($estados_subtareas->get('in progress') > 0 ? 'in progress' : 'to do' )],
                    'tasks_number' => $total_tareas,
                    'tasks_completed' => $total_tareas_completadas,
                    'percentage' =>  $total_tareas > 0 ? ((float)$total_tareas_completadas / (float)$total_tareas) * 100 : 0
                ]);
                $todas_tareas_clickup = $todas_tareas_clickup->merge($tareas_ordenadas);
            }   
        }

        $tareas_gantt = [];

        $nombres_estados = ['to do' => 'Pendiente', 'in progress' => 'En curso', 'complete' => 'Completada', 'not defined' => ''];
        foreach ($todas_tareas_clickup as $tarea) {
            // Fechas de inicio y fin
            $fecha_inicio = isset($tarea['start_date']) ? Carbon::createFromTimestampMs($tarea['start_date'])->startOfDay()->format('Y-m-d H:i:s') : null;
            $fecha_termino = isset($tarea['due_date']) ? Carbon::createFromTimestampMs($tarea['due_date'])->endOfDay()->format('Y-m-d H:i:s') : null;

            // Normaliza status
            $status = $nombres_estados[$tarea['status']['status']];

            $datos_tarea = [
                'id' => $tarea['id'],
                'text' => $tarea['name'],
                'start_date' => $fecha_inicio,
                'end_date' => $fecha_termino,
                'assigned' => $tarea['assigned'] ?? '',
                'parent' => $tarea['parent'] ?? 0,
                'progress' => ($status === 'completada') ? 1 : 0,
                'status_label' => $status,
                'open' => true
            ];

            if ($fecha_termino === null) {
                unset($datos_tarea['start_date']);
                unset($datos_tarea['end_date']);
                $datos_tarea['unscheduled'] = true;
            }
            array_push($tareas_gantt, $datos_tarea);
        }

        $listas_clickup = $todas_tareas_clickup->where('type', 'lista');

        $nombres_perfiles = $listas_clickup->pluck('name')->toArray();
        $porcentaje_perfiles = $listas_clickup->pluck('percentage')->toArray();

        $maximo_modulos = $todas_tareas_clickup->where('type', 'modulo')->countBy('parent')->max();
        $modulos = $todas_tareas_clickup->where('type', 'modulo')->groupBy('parent')->pluck('*.name')->values();
        if ($modulos->count() == 1) { //Si solo existe un perfil entonces todos los modulos se vuelven un arreglo
            $modulos = $modulos->map(function($perfil) {
                foreach($perfil as $indice => $modulo) {
                    $perfil[$indice] = [$modulo];
                }
                return $perfil;
            });
        }
        $modulos = array_map(null, ...collect(array_fill(0, $modulos->count(), array_fill(0, $maximo_modulos, '')))->replaceRecursive($modulos)->toArray());
        array_unshift($modulos, $nombres_perfiles);

        //$total_tareas = $listas_clickup->sum('tasks_number');
        $total_tareas_completadas = $listas_clickup->sum('tasks_completed');
        //$porcentaje_avance = $total_tareas > 0 ? round(($total_tareas_completadas / $total_tareas) * 100, 2) : 0;

        $ultima_tarea = $todas_tareas_clickup->filter(function ($tarea) {
            return $tarea['status']['status'] == 'complete' && $tarea['type'] == 'tarea';
        })->sortByDesc('date_done')->first() ?? ['task_name' => '', 'name' => ''];

        return view('documentacion_desarrollo', compact('roles', 'tareas_gantt', 'modulos', 'nombres_perfiles', 'porcentaje_perfiles', 'total_tareas_completadas', 'ultima_tarea'));
    }
}

class PlantUML {
    public static function codificar($texto) 
    {
        return (new static())->codificar_texto($texto);
    }

    private function codificar_texto($texto)
    {
        $datos = mb_convert_encoding($texto, 'UTF-8');
        $datos_comprimidos = gzdeflate($datos, 9);
        return $this->codificar64($datos_comprimidos);
    }

    private function codificacion6bit($bit)
    {
        if ($bit < 10) {
            return chr(48 + $bit);
        }
        $bit -= 10;
        if ($bit < 26) {
            return chr(65 + $bit);
        }
        $bit -= 26;
        if ($bit < 26) {
            return chr(97 + $bit);
        }
        $bit -= 26;
        if ($bit == 0) {
            return '-';
        }
        if ($bit == 1) {
            return '_';
        }
        return '?';
    }

    private function concatenar3bytes($bit1, $bit2, $bit3)
    {
        $caracter1 = $bit1 >> 2;
        $caracter2 = (($bit1 & 0x3) << 4) | ($bit2 >> 4);
        $caracter3 = (($bit2 & 0xF) << 2) | ($bit3 >> 6);
        $caracter4 = $bit3 & 0x3F;
        $texto_codificado = '';
        $texto_codificado .= $this->codificacion6bit($caracter1 & 0x3F);
        $texto_codificado .= $this->codificacion6bit($caracter2 & 0x3F);
        $texto_codificado .= $this->codificacion6bit($caracter3 & 0x3F);
        $texto_codificado .= $this->codificacion6bit($caracter4 & 0x3F);
        return $texto_codificado;
    }

    private function codificar64($caracter)
    {
        $texto_codificado = "";
        $tamanio_cadena = strlen($caracter);
        for ($iteracion = 0; $iteracion < $tamanio_cadena; $iteracion+=3) {
            if ($iteracion + 2 ==$tamanio_cadena) {
                $texto_codificado .= $this->concatenar3bytes(ord(substr($caracter, $iteracion, 1)), ord(substr($caracter, $iteracion + 1, 1)), 0);
            } else if ($iteracion + 1 == $tamanio_cadena) {
                $texto_codificado .= $this->concatenar3bytes(ord(substr($caracter, $iteracion, 1)), 0, 0);
            } else {
                $texto_codificado .= $this->concatenar3bytes(ord(substr($caracter, $iteracion, 1)), ord(substr($caracter, $iteracion + 1, 1)),
                    ord(substr($caracter, $iteracion + 2, 1)));
            }
        }
        return $texto_codificado;
    }
}
