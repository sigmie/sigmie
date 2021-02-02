# Forms


## Guidelines

Model relations are nested form fields eg.

```json
{
   name: "foo" ,
   desc: "bar",
   relation: {
       name: "relation foo",
       desc: "relation bar
   }
}
```

## Inertia Form
All form fields are **top level**.
For nested fields we use the `transform` method to
format as we wish.
