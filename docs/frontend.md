# Frontend


## Folder structure

```
- views
    - auth //Authentication views
    - cluster // Cluster views
    - dashboard // Dashboard view and components
    - layouts // Application layouts
    - legal // Legal texts
    - navbar // Application navigation bar
    - newsletter // Newsletter views
    - project // Project views
    - sidebar // Application sidebar
    - subscription // Subscription and payments views
    - token // Token views
    - user // User account views
```

## View structure rules
* Components are prefixed with an underscore `_`.
* Views don't have a prefix.
* Views and their components belong to a sub folder with the view name.
* A folder can contain:
  * **Only** folders
  * **Only** views
  * A view with it's components
* If views in different folders share the same components create a folder on the same
level as the views folder named `_shared` which will contain the shared components

**Don't try to imitate the php domains in the view since it's a different context.**

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

## Form fields

### Select
```
<form-select
  v-model.trim="$v.version.$model"
  :validations="$v.version"
  label="Version"
  name="version"
  id="version"
  aria-label="Elasticsearch version"
  :items="{
    '7.3': { id: 1, name: '7.3' },
  }"
></form-select>
```

### Input
```
<form-input
  suffix="GB"
  type="text"
  :value="size"
  @change="sizeChanged"
  label="Disk size"
  class="max-w-sm"
  :error-messages="errorMessages.size"
  id="disk"
  name="disk"
></form-input>
```
