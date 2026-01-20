@if ($item['submenu'] == [])
    @can($item['link'])
        <li class="nav-item">
            <x-nav-link href="{{ URL::to($item['link']) }}" :active="request()->is($item['link'])">
                <div class="inline-block" style="width: 160px !important;">{{ $item['menu'] }}</div>
                @if ($item['icono'] !== null && $item['icono'] !== '') 
                    <i class="{{ $item['icono'] }} transicion shortmenu"></i>
                @else 
                    <span class="transicion shortmenu">{{ ucfirst(substr($item['menu'], 0, 1)) }}</span>
                @endif
            </x-nav-link>
        </li>
    @endcan
@else
@php
    $linksArray=array();
    foreach($item['submenu'] as $keySB=>$valuesSB){
        $linksArray[]=$valuesSB['link'];
    }
@endphp
    @canany($linksArray)
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown{{ $item['idmenu'] }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="inline-block" style="width: 160px !important;">{{ $item['menu'] }}</div>
                @if ($item['icono'] !== null && $item['icono'] !== '') 
                    <i class="{{ $item['icono'] }} transicion shortmenu"></i>
                @else 
                    <span class="transicion shortmenu">{{ ucfirst(substr($item['menu'], 0, 1)) }}</span>
                @endif
            </a>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdown{{ $item['idmenu'] }}">
                @foreach ($item['submenu'] as $indexsm => $submenu)
                    @if ($submenu['submenu'] == [])
                        @can($submenu['link'])
                            @if ($indexsm>0)
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item" href="/{{ $submenu['link'] }}">{{ $submenu['menu'] }}</a></li>
                        @endcan
                    @endif
                @endforeach
            </ul>
        </li>
    @endcanany
@endif
