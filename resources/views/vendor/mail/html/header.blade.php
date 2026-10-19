@props(['url'])
@php($logo = config('mail.logo_url') ?: rtrim((string) config('app.url'), '/').'/educator-connect-logo.png')
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logo }}" class="logo" width="181" height="40" alt="{{ config('app.name') }}" style="height: 40px; width: 181px; max-height: 40px; border: 0;">
</a>
</td>
</tr>
