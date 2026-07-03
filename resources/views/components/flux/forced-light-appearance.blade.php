{{--
    Midnight Finance uses light surfaces (cards, tables, filters). Flux defaults to
    "system" appearance, which applies dark: utilities on macOS dark mode and breaks
    contrast on those surfaces. Always force light appearance in app layouts.
--}}
@fluxAppearance
<script>
    window.Flux.applyAppearance('light');
</script>
