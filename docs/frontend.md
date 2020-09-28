# Frontend


## Folder structure

```
- views
	- layouts // Layout templates
		- app // In App layout
			- app.vue // Layout
			- navbar.vue // Navbar component
			- sidebar.vue // Sidebar component
		- public // Layout for guest users
			- footer.vue // Footer component
			- public.vue // Layout
	- token // Token views
	- settings // ???
	- legal // Legal texts like imprint, terms, etc...
	- subscription //Subscription views
	- dashboard // Dashboard
	- cluster // Cluster views
	- project // Projects views
	- navbar // Navigation bar components
	- landing // Landing page
```

## Sidebar

Use the following to add a new sidebar item
```js
{
  text: "Playground",
  name: "playground",
  routeParams: {
    project:
      this.$page.project_id === null ? "" : this.$page.project_id,
  },
  icon: "puzzle",
  badge: {
    text: "Soon",
    color: "blue",
  },
}
```

## Clickaway

Bellow is the click away usage

```html
  <div v-on-clickaway="()=> $emit('away')">
  </div>
```