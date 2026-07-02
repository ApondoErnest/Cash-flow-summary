const mobileSidebarQuery = () =>
    window.matchMedia('(max-width: 1023px)').matches;

const openMobileSidebar = () =>
    document.querySelector(
        '[data-flux-sidebar][data-flux-sidebar-on-mobile]:not([data-flux-sidebar-collapsed-mobile])',
    );

const closeMobileSidebarIfOpen = () => {
    if (! mobileSidebarQuery() || ! openMobileSidebar()) {
        return;
    }

    document.dispatchEvent(new CustomEvent('flux-sidebar-toggle'));
};

document.addEventListener('livewire:navigated', closeMobileSidebarIfOpen);
