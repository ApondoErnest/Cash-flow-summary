@props([
    'label' => '',
])

<div class="mf-mobile-record-card__detail">
    <dt>{{ $label }}</dt>
    <dd>{{ $slot }}</dd>
</div>
