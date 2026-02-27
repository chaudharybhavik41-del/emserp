{{ $exception->request()->method() }} {{ \Illuminate\Support\Str::start($exception->request()->path(), '/') }}

## Headers
@php
    $headers = $exception->requestHeaders() ?? [];
@endphp

@if(empty($headers))
No header data available.
@else
@foreach($headers as $key => $value)
* **{{ $key }}**: {!! $value !!}
@endforeach
@endif

@php
    $body = null;
    if (method_exists($exception, 'requestBody')) {
        $body = $exception->requestBody();
    }
@endphp

@if(!empty($body))
## Body
{{ $body }}
@endif
