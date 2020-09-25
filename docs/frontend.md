# Frontend

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