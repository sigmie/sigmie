# Testing

## Factories
Use the code bellow in feature tests
```
$user = factory(Subscription::class)->create()->billable;
$project = factory(Project::class)->create(['user_id' => $user->id]);
$cluster = factory(Cluster::class)->create(['project_id' => $project->id]);
```