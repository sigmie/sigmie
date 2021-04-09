# Cluster Jobs

There are some rules regarding the `Cluster` jobs to avoid colissions. 
 1. There is only one job allowed to run per Cluster.
 2. Only type of job is allowed to exist in the queue. 

### 1 Job per Cluster 
To ensure that only 1 Job is running for each Cluster, we create a lock
with the `App\Jobs\Cluster\ClusterJob` and the cluster id.

When the jobs are handled the job tries to acquire the lock and if it **doesnt'** succeed
a new instance of the same job is dispatched to queue with a small delay waiting for the lock
to be released.

This aproach ensures that only one Cluster operation is executed at a time.

### 1 Task in queue 
Each time that we dispatch a Cluster job, we aquire a lock for the specific action. 
For example: `App\Jobs\Cluster\UpdateClusterAllowedIps`. If the lock can't be aquired an exception is thrown since the application shouldn't allow this kind of attempts.

This ensures that the Job `App\Jobs\Cluster\UpdateClusterAllowedIps` can be queue only once for
each cluster.

### Releasing lock

Both lock are released in 10 minutes since if they are still aquired it will mean than the job has failed and it also failed to release the lock.

The Job locks are always released even if the Job fails. The Cluster job can only be attempted once since we don't want to block the queue if a Job is failing.
