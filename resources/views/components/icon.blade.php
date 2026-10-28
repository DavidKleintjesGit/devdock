@props(['name', 'small' => false])

<svg class="ic{{ $small ? ' s' : '' }}" aria-hidden="true"><use href="#i-{{ $name }}"/></svg>
