@props(['tipo' => 'text', 'id'=> '', 'name'=> '', 'obligatorio'=>true,
'ancholabel'=>4, 'classlabel'=>'', 'attrlabel'=>'', 'label'=>'Label', 'preclassancholabel'=>'-sm',
'anchocampo'=>8, 'classcampo'=>'', 'attrcampo'=>'', 'classdivcampo'=>'', 'placeholder'=>'', 'min'=>'', 'max'=>'', 'minlength'=>'', 'maxlength'=>'', 'preclassanchocampo'=>'-sm',
'classdiv'=>'','attrdiv'=>'', 'mbdivcontent'=>'mb-3',
'content'=>'', 'rows'=>4,
'contenedor' => false, 'classaddcontenedor' => '', 'attraddcontenedor' => '',
'addElements'=>'', 'attrdivcampo'=>'',
'addPostElementsDiv'=>'', 'addPreElementsDiv'=>'',
'value'=>'','valueSelectDefault'=>'',
'regex'=>'','error_msg'=>''
])

@php
    $addclassdivcontent="";
    if($tipo==="divcontent"){ $addclassdivcontent='mb-3 row'; }

    if($min!==''){ $min=' min='.$min.' '; }
    if($max!==''){ $max=' max='.$max.' '; }
    if($minlength!==''){ $minlength=' minlength='.$minlength.' '; }
    if($maxlength!==''){ $maxlength=' maxlength='.$maxlength.' '; }
    if($name===""){ $name=$id; }
    if($obligatorio==="false"){ $obligatorio=false; }

@endphp

@if($contenedor)
    <div class="{{ $classaddcontenedor }}"  id="divcontenedor{{ $id }}" >
@endif

    <div class="row {{ $mbdivcontent }} {{ $addclassdivcontent }} {{ $classdiv }}" {{ $attrdiv }} id="div{{ $id }}">

        @switch($tipo)
            @case('divisorh')
                    <h5 class="{{ $classdiv }}" {{ $attrdiv }}>{{ $label }}</h5>
                @break
            @case('divcontent')
                    {!! $content !!}
                @break
            @case('divcontentlabel')
                    <label class="col-form-label col{{ $preclassancholabel }}-{{ $ancholabel }} {{ $classlabel }}" {{ $attrlabel }} id="label{{ $id }}" >{{ $label }}</label>
                    <div class="col{{ $preclassanchocampo }}-{{ $anchocampo }} {{ $classcampo }}" {{ $attrcampo }} id="{{ $id }}">{!! $content !!}</div>
                @break
            @case('text')
            @case('password')
            @case('number')
            @case('date')
            @case('time')
            @case('datetime')
            @case('datetime-local')
                    <label class="col-form-label col{{ $preclassancholabel }}-{{ $ancholabel }} {{ $classlabel }}" {{ $attrlabel }} id="label{{ $id }}" for="{{ $id }}">{{ $label }}</label>
                    {!! $addPreElementsDiv !!}
                    <div class="col{{ $preclassanchocampo }}-{{ $anchocampo }} {{ $classdivcampo }}" {{ $attrdivcampo }}>
                        <input {{ $placeholder }} {{ $attrcampo }} {{ $min }} {{ $max }} {{ $minlength }} {{ $maxlength }} class="form-control @if ($obligatorio) required @endif  {{ $classcampo }}" type="{{ $tipo }}" id="{{ $id }}" name="{{ $name }}" @if ($value!=="") value="{{ $value }}" @endif
                        @if ($regex!=="") regex="{{ $regex }}" @endif  @if ($error_msg!=="") error_msg="{{ $error_msg }}" @endif  /> {!! $addElements !!}
                    </div>
                    {!! $addPostElementsDiv !!}
                @break
            @case('select')
                    <label class="col-form-label col{{ $preclassancholabel }}-{{ $ancholabel }} {{ $classlabel }}" {{ $attrlabel }} id="label{{ $id }}" for="{{ $id }}">{{ $label }}</label>
                    {!! $addPreElementsDiv !!}
                    <div class="col{{ $preclassanchocampo }}-{{ $anchocampo }} {{ $classdivcampo }}" {{ $attrdivcampo }}>
                        <select {{ $attrcampo }} class="form-select @if ($obligatorio) required @endif  {{ $classcampo }} " data-placeholder=" @if ($placeholder!=="") {{ $placeholder }} @else Seleccione... @endif " id="{{ $id }}" name="{{ $name }}">
                            <option value="{{ $valueSelectDefault }}">@if ($placeholder!=="") {{ $placeholder }} @else Seleccione... @endif</option>
                            {{ $slot }}
                        </select> {!! $addElements !!}
                    </div>
                    {!! $addPostElementsDiv !!}
                @break
            @case('textarea')
                    <label class="col-form-label col{{ $preclassancholabel }}-{{ $ancholabel }} {{ $classlabel }}" {{ $attrlabel }} id="label{{ $id }}" for="{{ $id }}">{{ $label }}</label>
                    <div class="col{{ $preclassanchocampo }}-{{ $anchocampo }} {{ $classdivcampo }}" {{ $attrdivcampo }}>
                        <textarea rows="{{ $rows }}" {{ $attrcampo }} class="form-control  @if ($obligatorio) required @endif  {{ $classcampo }} "  {{ $minlength }} {{ $maxlength }} @if ($obligatorio) required @endif type="{{ $tipo }}" id="{{ $id }}" name="{{ $name }}"></textarea>
                    </div>
                @break
             @case('checkbox')
                    <label class="col-form-label col{{ $preclassancholabel }}-{{ $ancholabel }} {{ $classlabel }}" {{ $attrlabel }} id="label{{ $id }}" for="{{ $id }}">{{ $label }}</label>
                    <div class="col{{ $preclassanchocampo }}-{{ $anchocampo }} {{ $classdivcampo }}" {{ $attrdivcampo }} id="divcampo{{ $id }}">
                        <div class="input_wrapper">
                            <input {{ $attrcampo }} class=" {{ $classcampo }} " type="{{ $tipo }}" id="{{ $id }}" name="{{ $name }}" @if ($value!=="") value="{{ $value }}" @endif>
                            <label for="{{ $id }}"><span class="icon"></span></label>
                        </div>
                    </div>
                @break
            @case('checkbox2')
                    <label class="col-form-label col{{ $preclassancholabel }}-{{ $ancholabel }} {{ $classlabel }}" {{ $attrlabel }} id="label{{ $id }}" for="{{ $id }}">{{ $label }}</label>
                    <div class="col{{ $preclassanchocampo }}-{{ $anchocampo }} {{ $classdivcampo }}" {{ $attrdivcampo }}>
                        <div class="buttons-wrapper">
                            <label class="toggler-wrapper checkboxmod">
                              <input {{ $attrcampo }} class=" {{ $classcampo }} " type="{{ $tipo }}" id="{{ $id }}" name="{{ $name }}">
                              <div class="toggler-slider">
                                <div class="toggler-knob"></div>
                              </div>
                            </label>
                        </div>
                    </div>
                @break



        @endswitch

    </div>

@if($contenedor)
    </div>
@endif
