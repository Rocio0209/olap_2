import './bootstrap';

import jQuery from 'jquery';
window.$ = jQuery;

//Vacunas
import './vacunas/biologicos-preview.js';
import './vacunas/clues-bootstrap.js';
import './vacunas/sis-cubo.js';
// import './vacunas/clues-select2.js';
// import './vacunas/botones.js';

import * as bootstrap from 'bootstrap';

//Datatables
import 'datatables.net-responsive-bs5';
import "datatables.net-responsive";
import 'laravel-datatables-vite';

//Select2
import select2 from 'select2';
select2();

//momentJS
import moment from 'moment-timezone';
import 'moment/locale/es';
window.moment=moment;
moment.locale('es', {
    months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
    monthsShort: 'Enero._Feb._Mar_Abr._May_Jun_Jul._Ago_Sept._Oct._Nov._Dec.'.split('_'),
    weekdays: 'Domingo_Lunes_Martes_Miercoles_Jueves_Viernes_Sabado'.split('_'),
    weekdaysShort: 'Dom._Lun._Mar._Mier._Jue._Vier._Sab.'.split('_'),
    weekdaysMin: 'Do_Lu_Ma_Mi_Ju_Vi_Sa'.split('_')
    }
);

//DriverJS
import { driver } from 'driver.js';
window.driver = driver;

//dhtmlxGantt
import gantt from 'dhtmlx-gantt';
window.gantt = gantt;

//PptxGenJS
import pptxgen from 'pptxgenjs';
window.PptxGenJS = pptxgen;

//Funciones Universales
//*********************************Validaciones************************//
//funcion para validación de campos con clase "required"
//Requiere JQuery
//parametro form: id del formulario o elemento que contiene los capos a validar de la forma #id_elemento
//Devuelve: variable valid con valur tru o false.
window.valida=function(form){
    $('div.tiperror').remove();
    var valid=true;
    //Limpieza de los elementos para quitar clases y mensajes de error
    $(form+' input, '+form+' textarea ').each(function(){
        $(this).removeClass('errorstyle');
        $(this).removeAttr('placeholder');
    });
    $(form+' select').each(function(){
        $(this).removeClass('errorstyle');
        $("#select2-"+$(this).attr('id')+"-container").parent().removeClass('errorstyle');
    });

    //recorrido de los elementos <input type=X>
    $(form+' input.required').each(function(){
        //validar que el elemento no este vacio y cuente con un identificador
        if($(this).val()==="" && $(this).attr('id')){
            $(this).addClass('errorstyle');
            $(this).attr('placeholder','Campo Obligatorio');
            valid=false;
        }
    });

    //Validación de los input text con clase validemail para verificar direcciones de correos escritos correctamente
    $(form+' input.validemail').each(function(){
        //para los elementos con la clase validmail se verifica con la expresion regular indicada a continuacion.
        var regexemail = /^[\w-\.]{2,}@([\w-]{2,}\.)*([\w-]{2,}\.)[\w]{2,4}$/;
        if($(this).hasClass('required') || $(this).val()!==""){
            if (!regexemail.test($(this).val().trim())) {
                $(this).addClass('errorstyle').after('<div class="tiperror"> No es un dirección de correo válida</div>').attr('placeholder','Campo Obligatorio');
                valid=false;
            }
        }
    });

    //Validación del largo de la cadena en caso de que se tenga la clase validlenght
    $(form+' input.validlength').each(function(){
        var infoadicional="";
        var lengthcadena=$(this).val().length;
        if($(this).hasClass('required') || $(this).val()!==""){
            if($(this).attr('minlength')){
                if(parseFloat(lengthcadena)<parseFloat($(this).attr('minlength'))){
                    infoadicional="La cadena debe tener un mínimo de "+$(this).attr('minlength')+" caracteres";
                    $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }

            if($(this).attr('maxlength')){
                if(parseFloat(lengthcadena)>parseFloat($(this).attr('maxlength'))){
                    infoadicional="La cadena debe tener un máximo de "+$(this).attr('maxlength')+" caracteres";
                    $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }
        }
    });

    //Validacion si el contenido del texto es numerico
    $(form+' input.validanumero').each(function(){
        var infoadicional="";
        if($(this).hasClass('required') || $(this).val()!==""){
            if($(this).hasClass("validaentero")){
                //para los numeros enteros se verifica con la expresion regular indicada a continuacion.
                if($(this).hasClass("nocero")){
                    var regexemail =/^([1-9])*$/;
                    infoadicional=", no se admite valor 0 ";
                }else{
                    var regexemail =/^([0-9])*$/;
                }
                if (!regexemail.test($(this).val().trim())) {
                    $(this).addClass('errorstyle').after('<div class="tiperror"> No es un número entero válido'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }else{
                //validación estandar de un número utilizando isNaN
                if(isNaN($(this).val().trim())){
                    $(this).addClass('errorstyle').after('<div class="tiperror"> No es un número válido'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }else if($(this).val()==0 && $(this).hasClass("nocero")){
                    $(this).addClass('errorstyle').after('<div class="tiperror"> No es un número válido no se admite valor 0'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }

            if($(this).attr('min')){
                if(parseFloat($(this).val())<parseFloat($(this).attr('min'))){
                    infoadicional="El valor mínimo adminitido es "+$(this).attr('min');
                    $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }
            if($(this).attr('max')){
                if(parseFloat($(this).val())>parseFloat($(this).attr('max'))){
                    infoadicional="El valor máximo adminitido es "+$(this).attr('max');
                    $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }
        }
    });

    //validación de los valores max y min de input number
    $(form+' input[type="number"]').each(function(){
        var infoadicional="";
        if($(this).hasClass('required') || $(this).val()!==""){
            if($(this).attr('min')){
                if(parseFloat($(this).val())<parseFloat($(this).attr('min'))){
                    infoadicional="El valor mínimo adminitido es "+$(this).attr('min');
                    $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }
            if($(this).attr('max')){
                if(parseFloat($(this).val())>parseFloat($(this).attr('max'))){
                    infoadicional="El valor máximo adminitido es "+$(this).attr('max');
                    $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                    valid=false;
                }
            }
        }
    });

    //Validación de experesiones regulares en caso de que se tenga el atributo
    $(form+' input[regex]').each(function(){
        var regexstr = $(this).attr('regex');
        if($(this).hasClass('required') || $(this).val()!==""){
        var regex= new RegExp(regexstr);
            if (!regex.test($(this).val().trim())) {
                $(this).addClass('errorstyle').after('<div class="tiperror"> '+(($(this).attr('error_msg')!==undefined && $(this).attr('error_msg')!=="" )? $(this).attr('error_msg') : 'El Valor es Incorecto')+'</div>').attr('placeholder','Campo Obligatorio');
                valid=false;
            }
        }
    });

    //Validar el largo de las cadena
    $(form+' input[minlength], '+form+' textarea[minlength]').each(function(){
        var infoadicional="";
        var lengthcadena=$(this).val().length;
        if($(this).hasClass('required') || $(this).val()!==""){
            if(parseFloat(lengthcadena)<parseFloat($(this).attr('minlength'))){
                infoadicional="La cadena debe tener un mínimo de "+$(this).attr('minlength')+" caracteres";
                $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                valid=false;
            }
        }
    });
    $(form+' input[maxlength], '+form+' textarea[maxlength]').each(function(){
        var infoadicional="";
        var lengthcadena=$(this).val().length;
        if($(this).hasClass('required') || $(this).val()!==""){
            if(parseFloat(lengthcadena)>parseFloat($(this).attr('maxlength'))){
                infoadicional="La cadena debe tener un máximo de "+$(this).attr('maxlength')+" caracteres";
                $(this).addClass('errorstyle').after('<div class="tiperror">'+infoadicional+'. </div>').attr('placeholder','Campo Obligatorio');
                valid=false;
            }
        }
    });

    //recorrido de los elementos <select>
    $(form+' select.required').each(function(){
        $(this).removeClass('errorstyle');
        $(this).removeAttr('placeholder');
        $("#select2-"+$(this).attr('id')+"-container").parent().removeClass('errorstyle');
        //validar que el elemento no este vacio y cuente con un identificador
        if($(this).val()=="" && $(this).attr('id')){
            $(this).addClass('errorstyle');
            $("#select2-"+$(this).attr('id')+"-container").parent().addClass('errorstyle');
            valid=false;
        }
    });

    //recorrido de los elementos <textarea>
    $(form+' textarea.required').each(function(){
        $(this).removeClass('errorstyle');
        $(this).removeAttr('placeholder');
        //validar que el elemento no este vacio y cuente con un identificador
        if($(this).val()=="" && $(this).attr('id')){
            $(this).addClass('errorstyle');
            $(this).attr('placeholder','Campo Obligatorio');
            valid=false;
        }
    });

    return valid;
}

//#######Activar elementos posteriores a carga de la página
$(document).ready(function(){
    window.hidecarga();

    // Fix bug de tooltips que se quedan abiertos
    $('table').on('click','a',function(){
        $('div.tooltip.show').remove();
    });
});

/******************* Limpiar Formularios *******************/
window.clear_form=function(idform_reset){
    $(idform_reset).trigger("reset");
    $(idform_reset+' select').change().removeClass('errorstyle');
    $(idform_reset+' input').removeClass('errorstyle').attr('placeholder','');
    $(idform_reset+' span.select2-selection').removeClass('errorstyle');
}

//Precarga un objeto de datos en un un formulario
window.precargar_form = function(form, data, omitidos = []) {
    Object.keys(data).forEach((campo) => {
        if (omitidos.indexOf(campo) >= 0) { //Este campo se agrega de forma manual
            return;
        }
        let input = $(form).find('[name="' + campo + '"]');
        if (input.attr('type') == 'checkbox') {
            input.prop('checked', data[campo] == 1);
        } else if(input.attr('type') != 'file') {
            input.val(data[campo]);
        }
        if (input.attr('type') == 'checkbox' || input.is('select')) {
            input.trigger('change');
        }
    });
}

//Obtiene los ID de un elemento, los procesa en la vista y los regresa como un arreglo
window.gestionar_id = function(element, operacion=null, objetivo=null) {
    let ids={};

    $.each(element[0].attributes, function ( indice, atributo ) {
        if (atributo.name.includes('id-accion')) {
            let controlid = atributo.name.replace('id-accion', '');
            ids['id'+ controlid] = atributo.value;

            if (operacion === 'update') {
                $(objetivo +' fieldset').append('<input class="id2up" type="hidden" name="idold'+((controlid > 1 ? controlid : ''))+'" value="'+ atributo.value +'" />');
            } else if (operacion === 'delete') {
                $(objetivo).attr(atributo.name, atributo.value);       
            }
        }
    });

    return ids;
}

//Obtiene el mensaje de error y coloca las advertencias en los campos erroneos
window.mostrar_errores = function(data) {
    let mensaje="";

    //Mostrar los errores bajo de cada campo
    if(data.responseJSON!==undefined){
        //en caso de tener algún mensaje general se almacena para mostrarlo
        if(data.responseJSON.message!==undefined){
            mensaje='<br><h5 style="color:red;">'+data.responseJSON.message+'</h5>';
        }

        if(data.responseJSON.errors!==undefined){
            let errors = data.responseJSON.errors;
            $.each(errors,function(indexField,valuesErrors){
                let errorsPrint='<div class="tiperror"><ul>';
                $.each(valuesErrors,function(indexError,valueError){
                    errorsPrint+='<li>'+valueError+'</li>';
                });
                errorsPrint+="</ul></div>";
                let camposDinamicos = indexField.split('.');
                if (camposDinamicos.length <= 1) {
                    $('#'+indexField).addClass('errorstyle').after(errorsPrint);
                } else {
                    let indiceCampo = camposDinamicos[1]; //Solo soporta un arreglo unidimensional
                    let campoBase = camposDinamicos.shift();
                    let campoCompuesto = '[name="' + campoBase + camposDinamicos.map(element => '[' + element + ']').join('') + '"]';
                    $(campoCompuesto).addClass('errorstyle').after(errorsPrint);
                    $(campoCompuesto.replace(/[0-9]/g, '')).find(indiceCampo).addClass('errorstyle').after(errorsPrint);
                }
            });
            mensaje="";
        }
    }

    return mensaje;
}

//Mostrar Precarga Div
window.showcarga=function(vel='fast'){
    if(!$('#preCargaDiv').is(':visible')){
        $('#preCargaDiv').fadeIn(vel);
    }
}

//Ocultar Precarga Div
window.hidecarga=function(vel='fast'){
    $('#preCargaDiv').fadeOut(vel);
}

//Inicializar los elementos Select2
window.inicializaSelect2=function(props=null,identificador=".select2"){
    let propiedades={
        theme: 'bootstrap-5',
        language: {
            errorLoading: function () {
                return "No se pudieron cargar los resultados";
            },
            inputTooLong: function (e) {
                var n = e.input.length - e.maximum,
                    r = "Por favor, elimine " + n + " car";
                return (r += 1 == n ? "ácter" : "acteres");
            },
            inputTooShort: function (e) {
                var n = e.minimum - e.input.length,
                    r = "Por favor, introduzca " + n + " car";
                return (r += 1 == n ? "ácter" : "acteres");
            },
            loadingMore: function () {
                return "Cargando más resultados…";
            },
            maximumSelected: function (e) {
                var n = "Sólo puede seleccionar " + e.maximum + " elemento";
                return 1 != e.maximum && (n += "s"), n;
            },
            noResults: function () {
                return "No se encontraron resultados";
            },
            searching: function () {
                return "Buscando…";
            },
            removeAllItems: function () {
                return "Eliminar todos los elementos";
            },
        }
    }

    if(props!==null){
        if(props.parent!==null){
            propiedades.dropdownParent = $('#'+props.parent)
            delete Object.getPrototypeOf(props).parent;
        }
        propiedades = {...propiedades, ...props};
    }

    $('#'+props.parent+' select'+identificador).select2( propiedades );
}

//Inicializa un Driver JS
window.inicializaDriverJS=function(props) {
    let propiedades={
        progressText: '{{current}} de {{total}}',
        nextBtnText: 'Siguiente →',
        prevBtnText: '← Anterior',
        doneBtnText: 'Listo',
        showButtons: [
            'next',
            'previous',
        ],
    }

    propiedades = {...propiedades, ...props};

    return window.driver(propiedades);
}

/***************Remover Caracteres especiales de los Strings****************/
//Param: String a normalizar
//Return: String sin caratceres especiales
const removeSpecialC = (str) => {
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

/***************Remover Acentos de los Strings****************/
//Param: String a normalizar
//Return: String sin acentos
const removeAccents = (str) => {
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

/***************Remover Acentos de los Strings****************/
//Param: String a normalizar
//Return: String sin acentos
window.eliminarTildes=function(cadena)
{
	for(var i=0; i<cadena.length; i++)
	{
		cadena = cadena.replace("\xC0", 'A');
		cadena = cadena.replace("\xC1", 'A');
		cadena = cadena.replace("\xC2", 'A');
		cadena = cadena.replace("\xC3", 'A');
		cadena = cadena.replace("\xC4", 'A');
		cadena = cadena.replace("\xC5", 'A');
		cadena = cadena.replace("\xC8", 'E');
		cadena = cadena.replace("\xC9", 'E');
		cadena = cadena.replace("\xCA", 'E');
		cadena = cadena.replace("\xCB", 'E');
		cadena = cadena.replace("\xCC", 'I');
		cadena = cadena.replace("\xCD", 'I');
		cadena = cadena.replace("\xCE", 'I');
		cadena = cadena.replace("\xCF", 'I');
		//cadena = cadena.replace("\xD1", 'N');
		cadena = cadena.replace("\xD2", 'O');
		cadena = cadena.replace("\xD3", 'O');
		cadena = cadena.replace("\xD4", 'O');
		cadena = cadena.replace("\xD5", 'O');
		cadena = cadena.replace("\xD6", 'O');
		cadena = cadena.replace("\xD8", 'O');
		cadena = cadena.replace("\xD9", 'U');
		cadena = cadena.replace("\xDA", 'U');
		cadena = cadena.replace("\xDB", 'U');
		cadena = cadena.replace("\xDC", 'U');

		cadena = cadena.replace("\xE0", 'a');
		cadena = cadena.replace("\xE1", 'a');
		cadena = cadena.replace("\xE2", 'a');
		cadena = cadena.replace("\xE3", 'a');
		cadena = cadena.replace("\xE4", 'a');
		cadena = cadena.replace("\xE5", 'a');
		cadena = cadena.replace("\xE8", 'e');
		cadena = cadena.replace("\xE9", 'e');
		cadena = cadena.replace("\xEA", 'e');
		cadena = cadena.replace("\xEB", 'e');
		cadena = cadena.replace("\xEC", 'i');
		cadena = cadena.replace("\xED", 'i');
		cadena = cadena.replace("\xEE", 'i');
		cadena = cadena.replace("\xEF", 'i');
		//cadena = cadena.replace("\xF1", 'n');
		cadena = cadena.replace("\xF2", 'o');
		cadena = cadena.replace("\xF3", 'o');
		cadena = cadena.replace("\xF4", 'o');
		cadena = cadena.replace("\xF5", 'o');
		cadena = cadena.replace("\xF6", 'o');
		cadena = cadena.replace("\xF8", 'o');
		cadena = cadena.replace("\xF9", 'u');
		cadena = cadena.replace("\xFA", 'u');
		cadena = cadena.replace("\xFB", 'u');
		cadena = cadena.replace("\xFC", 'u');

		cadena = cadena.replace("\xB4", ' ');
		cadena = cadena.replace("\xA8", ' ');
		cadena = cadena.replace("\x60", ' ');
        }

	return cadena;
}

/***************Formatear String de números Flotantes****************/
//Param: numero: String a formatear
//Param: prefijo String a colocar como prefijo
//Param: postfijo String a colocar como postfijo
//Return: String en formato ###,###.##
window.format_number_float=function(numero,prefijo="",postfijo="",fixed=2){
    var numeroorig=parseFloat(numero);
    return prefijo+(numeroorig.toFixed(fixed).replace(/(\d)(?=(\d{3})+\.)/g, '$1,'))+postfijo;
}

/***************Formatear String de números Enteros****************/
//Param: numero: String a formatear
//Param: prefijo String a colocar como prefijo
//Param: postfijo String a colocar como postfijo
//Return: String en formato ###,###
window.format_number_int=function(numero,prefijo="",postfijo=""){
    var numeroorig=parseFloat(numero);
    return prefijo+(numeroorig.toFixed(1).replace(/(\d)(?=(\d{3})+\.)/g, '$1,').replace(".0",""))+postfijo;
}

//Funcion para LIMPIAR campos de formularios (quitar clases de error y valores)
//Requiere JQuery
//parametro campo: string del id del elemento que se desea limpiar
//Devuelve: void.
window.limpiar_campo=function(campo){
    $('#'+campo).val("").removeClass('errorstyle').attr('placeholder','');
}

//Redondear numero a n decimales
window.round=function(value, decimals) {
    return Number(Math.round(value+'e'+decimals)+'e-'+decimals);
}

//Truncar numero a n deciamles
window.toFixedTruncate=function(num, fixed) {
    var re = new RegExp('^-?\\d+(?:\.\\d{0,' + (fixed || -1) + '})?');
    return num.toString().match(re)[0];
}

//obtener nombre del mes basado en su ID
window.obtener_nombre_idmes=function(mes){
    var strmes ="";
    switch (mes) {
        case '01':
        case '1':
            strmes='Enero';
            break;
        case '02':
        case '2':
            strmes='Febrero';
            break;
        case '03':
        case '3':
            strmes='Marzo';
            break;
        case '04':
        case '4':
            strmes='Abril';
            break;
        case '05':
        case '5':
            strmes='Mayo';
            break;
        case '06':
        case '6':
            strmes='Junio';
            break;
        case '07':
        case '7':
            strmes='Julio';
            break;
        case '08':
        case '8':
            strmes='Agosto';
            break;
        case '09':
        case '9':
            strmes='Septiembre';
            break;
        case '10':
            strmes='Octubre';
            break;
        case '11':
            strmes='Noviembre';
            break;
        case '12':
            strmes='Diciembre';
            break;

        default:

            break;
    }

    return strmes;
}

//Poner asterisco a todos los campos required
window.asteriscoObligatorio=function(){
    $('input, select, textarea').each(function(){
        if($(this).hasClass('required')){
            var id=$(this).attr('id');
            var labelstr=$('#label'+id).text();
            if(!labelstr.includes('*')){
                $('#label'+id).append(' *');
            }
        }else{
            var id=$(this).attr('id');
            var labelstr=$('#label'+id).text();
            if(labelstr.includes('*')){
                $('#label'+id).text(labelstr.replace(" *","","gi"));
            }
        }
    });
}

//Función para determinar si se necesita ocupar un texto blanco o un texto dependiendo del fondo
window.color_fondo_claro=function(color_fondo) {
    let color = (color_fondo.charAt(0) === '#') ? color_fondo.substring(1, 7) : color_fondo;
    let rojo = parseInt(color.substring(0, 2), 16); // hexToR
    let verde = parseInt(color.substring(2, 4), 16); // hexToG
    let azul = parseInt(color.substring(4, 6), 16); // hexToB
    let uicolors = [rojo / 255, verde / 255, azul / 255];
    let rgb = uicolors.map((tonalidad) => {
        if (tonalidad <= 0.03928) {
            return tonalidad / 12.92;
        }
        return Math.pow((tonalidad + 0.055) / 1.055, 2.4);
    });
    let lambda = (0.2126 * rgb[0]) + (0.7152 * rgb[1]) + (0.0722 * rgb[2]);
    return lambda <= 0.179;
}

window.limpiar_campo=function(campo){
    $('#'+campo).val("").removeClass('errorstyle').attr('placeholder','');
}

//funcion para omitir espacios basado en el codigo del caracter
$(document).on('keypress','input.omitirEspacios',function(e) {
    var tecla = e.keyCode? e.keyCode : e.which;
    if(tecla == 32)
    {
        e.preventDefault();
        e.cancelBubble = true;
    }
});

//convretir a mayusculas los elementos con la clase "mayusculas"
$(document).on('keyup','input.mayusculas',function() {
    var datos = new String($(this).val());
    datos = datos.toUpperCase(datos);
    $(this).val(datos);
});

//Al abrir un select2 seleccionar automaticamente el cuadro de busqueda
$(document).on('select2:open', function(e) {
    document.querySelector(`[aria-controls="select2-${e.target.id}-results"]`).focus();
});


//********************Formatear FormData para enviar vía ajax********************//
//Función para formatear FormData a partir de los campos en un formulario
//param selector: selector de la forma "#id" para formatear el elemento indicado (debe ser form)
//param formData: objeto FormData donde se almacenará el valor recopilado de los campos
//return formData: objeto con los valores recopilados del Formulario
window.formatdatafromform = function(selector,formData){
    $(selector+' input, '+selector+' select, '+selector+' textarea').each(function(){
        if($(this).attr('id')!==undefined){
            if($(this).attr('type')==="checkbox"){
                formData.append($(this).attr('id'),($(this).is(':checked'))? 1 : 0);
            }else{
                formData.append($(this).attr('id'),$(this).val());
            }
        }
    });
}
