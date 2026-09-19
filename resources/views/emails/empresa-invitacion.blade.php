<h1>Invitacion empresarial</h1>

<p>Has sido invitado a unirte a {{ $invitacion->empresa->nombre_comercial ?? $invitacion->empresa->razon_social }}.</p>
<p>La invitacion expira el {{ $invitacion->expira_en->format('d/m/Y H:i') }}.</p>
<p><a href="{{ $url }}">Ver y aceptar invitacion</a></p>