# Blade Omni

**Blade Omni** is a Laravel package and Vite plugin for building universal, single-file Blade and Livewire components. The core goals of Omni are:

- **Unified API**: Write and use Blade and Livewire components using identical syntax and structure.
- **Single-File Components**: Combine the template, logic, styles, and scripts in one file, with Vite bundling.

All components—whether Blade or Livewire—can:

- Be mounted to a route as a full-page component  
- Define and typehint props the same way  
- Use layouts, slots, and attribute bags consistently  
- Include JS and CSS that’s omnid by Vite  
- Be rendered and included using the same syntax and helper  
- Define `mount`, `shouldRender`, and `with` methods  
- Define helper functions that can be used in templates

Omni makes it trivial to convert a Blade component into a Livewire one: Simply switch the class to extend Livewire, then add the behaviour. Your file structure, props, and other Blade features continue to work the same way.