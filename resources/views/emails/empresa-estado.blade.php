<h1>Solicitud empresarial actualizada</h1>

<p>La solicitud de {{ $empresa->nombre_comercial ?? $empresa->razon_social }} ha sido marcada como <strong>{{ $decision }}</strong>.</p>
@if ($empresa->motivo_estado)
    <p>Motivo: {{ $empresa->motivo_estado }}</p>
@endif
<p>Puedes consultar el estado desde tu panel empresarial.</p>