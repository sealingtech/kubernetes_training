# Using Kubernetes
In this lab, we will be deploying the containers we made in the Docker lab to Kubernetes.  We will be using images posted in Docker hub specifically for this class made using the same process.  For both Apache and Mariadb a service and pod will be created.  For Apache an Ingress will be created in order to access the services from outside of the network.  Finally we will demonstrate rolling updates and how you can use Kubernetes to manage multiple versions of the software.

## To start this lab

Start Kubernetes in the directory.  Kubernetes files are written in YAML files.  To save time, all the files have been created for you, but we will go through each one by one and explain the purpose.  In the YAMLs are comments which should also be looked at.  Due to the amount of time given to the class, this is definitely a fire hose.  More time will be required to research the inividual options in each YAML.

Run the command:

```
cd ~/kubernetes_training/kubernetes/
ls -la
```

## Deploy Database Service    
In order for containers to communicate they must talk through a service.  In this design our web pods need to be able to communicate with our database container so they need a service IP.  Each host is running a container called "kube-proxy".  Kube-proxy's role is to intercept requests going to the service IPs and send them to the correct pods.  This service also performs load balancing.

Some notes:
1. The name of the service is important as this is the name that containers will lookup to find the service IP.  Kubernetes runs a DNS server container called kube-dns.  Each service name can been looked up using this with the service name.
2. We specify a port name (which is mostly for convenience) then the port which the service IP will listen on.  When it receives a request to this port it will send the request to the pods target port which will service it.
3. The selector is important.  We will create forward traffic to all pods with the tag app=dbtraining.  
4. It is best practice to deploy services first.  The reason for this is additional environment variables can be set on pods containers which can then be used with scripting.

File:
```
apiVersion: v1
kind: Service
metadata:
  name: db-service
  namespace: default
spec:
  ports:
    - name: mariadb
      #Listen on 3306 and then forward the request to port 3306 in the pod
      port: 3306
      targetPort: 3306
  #Send requests in a load balanced fashion to containers with the selector app=dbtraining
  selector:
    app: dbtraining
```

To create the service apply the db_service.yaml and then run get service to see our service created with its assigned IP address.

Run the command:

```
kubectl apply -f db_service.yaml
```

Output:

```
service "db-service" created
Daniels-MBP:kubernetes dlohin$ kubectl get service
NAME         TYPE        CLUSTER-IP     EXTERNAL-IP   PORT(S)    AGE
db-service   ClusterIP   10.96.40.232   <none>        3306/TCP   5m
kubernetes   ClusterIP   10.96.0.1      <none>        443/TCP    6h
```


## Deploy Database Pod

First we need to create a Deployment.  A deployment will create an additional replicaset which will contain a single pod (replicas are one in this case).  This pod only has a single container which is the database Docker container from Docker hub (it is the same as we created in the Docker training).  There are a few things to point out in this file:
1. We are creating a label with the key "app" and the value of dbtraining.  We do this so we can refer to this deployment and pods in other yaml files.  For example, the service will point to the pods with this tag.  The tags can be anything, 
2. In the containers, we are setting the environment variables like we did in Docker.  These are set by the container image itself.  Because we started with the mariadb container these are available to us as options.
3. In the image setting we are pulling from Docker Hub and the version is version 1.  We can use this for configuration management.

File:

```
apiVersion: extensions/v1beta1
kind: Deployment
metadata:
  name: dbtraining
  namespace: default
  #Labels are used as "tags" that can be referenced in other object (like services).  Labels can be anything, environment, beta or whatever you want.
  labels:
    app: dbtraining
spec:
  #Create two pods inside of the cluster, and ensure two are always running
  replicas: 1
  #This spec applies to Deployments with the label app=dbtraining
  selector:
    matchLabels:
      app: dbtraining
  template:
    metadata:
      name: dbtraining
      labels:
        app: dbtraining
    #This replicaset has these containers
    spec:
      containers:
      - name: dbtraining
        #Remember those environment varialbe we set in Docker?  Same thing here...
        env:
        - name: MYSQL_ROOT_PASSWORD
          value: "SuperSecretPassword"
        - name: MYSQL_DATABASE
          value: "applications"
        - name: MYSQL_USER
          value: "docker_man"
        - name: MYSQL_PASSWORD
          value: "OnlySlightlySecret"
        imagePullPolicy: Always
        image: dlohin/stechtraining_db:1
        ports:
        - containerPort: 3306
      restartPolicy: Always
      dnsPolicy: ClusterFirst
```
   
To apply this file.

Run the command:

```
kubectl apply -f db_pod.yml
```   

To view the status of this, we can look at a few things.  We see that a deployment has been created.  There is a desired one replica in this deployment (which we set).  Currently there is one configured (which is good).

Run the command:

```
kubectl get deployments
```

Output

```
NAME         DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
dbtraining   1         1         1            1           7m
```

We can see the replicaset with the command kubectl get replicaset.  Here we see the desired number of pods that are desired, current, ready.

Run the command:

```
kubectl get replicaset
```


Output:

```
NAME                    DESIRED   CURRENT   READY     AGE
dbtraining-77c7cf7cc6   1         1         1         8m
```
   
We can see that there is one pod that is created.  The ready column shows the number of containers running and the containers desired.

Run the command:

```
kubectl get pods
```

Output:

```
NAME                          READY     STATUS    RESTARTS   AGE
dbtraining-77c7cf7cc6-vf2cx   1/1       Running   0          10m   
```
 
 To get more information about a specific pod we can use the describe verb in kubectl (your name will differ so you need to change it accordingly).
 
 Run the command:
 
```
kubectl describe pod dbtraining-77c7cf7cc6-vf2cx
```

Here you can see all the specific information about the specific pod as well events.

Docker convention is to output logs to STDOUT in Linux.  When this is done it is possible to print logs using kubectl.

Run the command:

```
kubectl logs dbtraining-77c7cf7cc6-vf2cx
```
  
  Similiar to Docker, you can pull a shell on the container to exit, use ctrl + a then d.
  
Run the command:

```
kubectl exec -it dbtraining-77c7cf7cc6-vf2cx bash  
```
  

## Deploy configuration Map
If you remember when we created our Docker application the php application had a configuration file in it containing information to connect to the database.  While it is possible to have preset all these values in the Docker container that is not a good idea because our passwords are now in the Docker container that is public on Docker hub which anyone can view.  We also changed the hostname (we used mariadb in Docker, we are now using db-service in our service).  We could have also created scripts that set environment variables to fix these similiar to what was done in the MariaDB containers.    Instead of using those two options, we will use a Kubernetes configmap.  When the container is created we will use this to mount a volume which contains these files over what is currently in the container.  This configmap only specifies a single file called config.php.  Here we specify our service name, and password.  This configmap will then be attached to the volume and then mounted in the container.

File:

```
apiVersion: v1
kind: ConfigMap
metadata:
  name: web-config
apiVersion: v1
data:
  config.php: |
    <?php

    $hostname = "db-service";
    $username = "docker_man";
    $password = "OnlySlightlySecret";
    $db = "applications";

    ?>
```

Apply if this file:

Run the command:

```
kubectl apply -f web_configmap.yaml
```

## Deploy Web Service
Similar to the db service, we need to create a web service.  This will make the service available to the entire cluster, but will not expose this to outside of the cluster.  This will be used by the Ingress.

File: 

```
apiVersion: v1
kind: Service
metadata:
  name: web-service
  namespace: default
spec:
  ports:
    - name: http
      #Listen on 3306 and then forward the request to port 3306 in the pod
      port: 80
      targetPort: 80
  #Send requests in a load balanced fashion to containers with the selector app=dbtraining
  selector:
    app: webtraining
```

Run the command:

```
kubectl apply -f web_service.yaml
```



## Deploy Web Pod
Now we will deploy the web pods.  This is similar to the database pods we created earlier with a few changes.  

1. Our replicaset will have two pods deployed instead of one this time.  Requests to the service IP will then be load balanced to one of these two.
2. Our image again is version 1, in this class we will update to version 2 in a later step.
3. We have a liveness probe and readiness probe. These are critical for properly handling failure of pods, updating of pods and more.  The readiness probe will be how Kubernetes know when the the pod is ready to service requests.  Kubernetes will not forward traffic to this pod until the readiness check comes back correctly.  The Liveness probe is run after the readiness probe comes back.  When Kubernetes senses a failure it will stop sending traffic to the pod, then delete the pod and recreate a new one in hopes that this will solve any issues.
4. There is a volume and volume mount indicated which is our configmap.  The volume is made available to the pod and then this volume gets mounted to a directory inside of out container using the volume mount.

File:

```
apiVersion: extensions/v1beta1
kind: Deployment
metadata:
  name: webtraining
  namespace: default
  #label this deployment as web training so we can reference it later
  labels:
    app: webtraining
spec:
  #This Deployment will have a replicaset that configures two pods across the cluster
  replicas: 2
  selector:
    matchLabels:
      app: webtraining
  template:
    metadata:
      name: webtraining
      labels:
        app: webtraining
    spec:
      containers:
      - name: webtraining
        imagePullPolicy: Always
        image: dlohin/stechtraining_web:1
        ports:
        #Open up ports 80
        - containerPort: 80
        #Readiness probe will have the LB check until a successful httpGet to / is made before it begins to send traffic to newly created pods
        readinessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 5
          timeoutSeconds: 1
          periodSeconds: 15
        #Once pods are living the LB will be configured to verify that each container is up by making a get request.  Pods are killed and recreated if it fails
        livenessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 5
          timeoutSeconds: 1
          periodSeconds: 15
        #The pod contains a volume called web-config and will be mounted inside of the container here
        volumeMounts:
          - mountPath: /var/www/html/config
            name: web-config
      #Pod has a volume made available to it, in this case it is a configmap that was created earlier
      volumes:
      - name: web-config
        configMap:
          name: web-config
      restartPolicy: Always
      dnsPolicy: ClusterFirst
```

Run the command:

```
kubectl apply -f web_configmap.yaml
```


Notice when we get deployments we now have two pods instead of one?

Run the command:

```
kubectl get deployments
```

Output:

```
NAME          DESIRED   CURRENT   UP-TO-DATE   AVAILABLE   AGE
dbtraining    1         1         1            1           44m
webtraining   2         2         2            2           6m
```

## Configure Ingress
To expose our web service outside of the cluster an Ingress will be created.  It will be necessary to change the host: option to the dns name you have been provided.

File:
```
apiVersion: extensions/v1beta1
kind: Ingress
metadata:
  name: web-service-ingress
  namespace: default
  annotations:
    #Use Traefik as the ingress load balancer
    kubernetes.io/ingress.class: traefik
spec:
  rules:
  #This reverse web proxy will look for requests looking for a specific host name so that you can host multiple sites on the same cluster all on port 80.  Therefore it is necessary to have a proper DNS record pointed to your system.  For the class this has been done for you.
  - host: test.lohin.lan
    http:
      paths:
      #Specify this service to forward traffic to
      - backend:
          serviceName: web-service
          servicePort: 80
```

Run the command
```
kubectl apply -f ingress.yaml
```


Are you now sitting here thinking that was way to many commands to run???  All those kubectl applys, I thought this was supposed to be one touch and highly repeatable?  We could have actually consolidated this all into one file and then seperated them with three dashes (---) or you could have run the command kubectl apply -f . and it would have applied all the yamls in the correct order, but what is the fun in that?  Really the purpose was to show how these fit together so we did it one at a time the hard way like we used to do it in the old days!  Now get off my lawn!


Open up the web browser and go to the dns name provided to you in the class.



## Scaling containers

If we find we need more web containers, simply scale the deployment which will create more pods then view the pods and you should have four now.

Run the commands:

```
kubectl scale deployment webtraining --replicas=4
kubectl get pods
```

Lets scale it back down to two for now.

Run the commands:

```
kubectl scale deployment webtraining --replicas=2
```


## Rolling Updates
This is coolest feature of Kubernetes, period. I saw this in a presentation and it the primary reason I wanted to learn Kubernetes. 

First we want to prove that this works.  Open up a new terminal and run this loop command and leave it running so you can see it.  What you should see is a message showing that we are running version 1 of the container as well as the container name.  Because we have two containers and the load balancer is alternating between the two you should see it bouncing back and forth between them.  You will need to change the test.lohin.lan to the dns name that was provided to you.

Run the command:
```
while true; do curl http://test.lohin.lan/version.php;echo "";sleep 1;done;
```

Output will look something like this:

```
This is version 1 webtraining-8688bf8894-7bxv4
This is version 1 webtraining-8688bf8894-xlz9z
This is version 1 webtraining-8688bf8894-7bxv4
This is version 1 webtraining-8688bf8894-xlz9z
```

Now lets update the container.  This will create a new replicaset then start a new pod. Kubernetes will then make sure it is ready (remember that we set the readiness probe) and then being forwarding traffic to it.  Once this occurs, it will delete and old container and then move on to the next one until we have our desired number of pods set in our deployment file (2 in our case)

```
kubectl set image deployment/webtraining webtraining=dlohin/stechtraining_web:2
```

To see the status of the rollout you can run rollout status.

Run the command:
```
kubectl rollout status deployment/webtraining
```

Output:
```
Waiting for rollout to finish: 1 of 2 updated replicas are available...
deployment "webtraining" successfully rolled out
```

If you look at your looping curl statement you will see the version number update and the container names should switch over to something else.

```
This is version 1 webtraining-8688bf8894-7bxv4
This is version 1 webtraining-8688bf8894-7bxv4
This is version 2 webtraining-6677c8b95f-vhwq6
This is version 2 webtraining-6677c8b95f-vhwq6
This is version 2 webtraining-6677c8b95f-vhwq6
This is version 2 webtraining-6677c8b95f-vhwq6
This is version 2 webtraining-6677c8b95f-spcpl
```

You can view the rollout history to see how we updated this
```
Daniels-MBP:~ dlohin$ kubectl rollout history deployment webtraining
deployments "webtraining"
REVISION  CHANGE-CAUSE
1         <none>
2         <none>
```

Lets look at our replicasets.  You can see we now have two replicasets.  The old replicaset now has a desired number of zero, so it running no pods and our new replicaset has taken it's place. 

NOTE: rs in this is a shortcut for replicaset, now that you are getting good, I will show you this shortcut.   

Run the command:
```
kubectl get rs
```

Output:

```
NAME                     DESIRED   CURRENT   READY     AGE
dbtraining-77c7cf7cc6    1         1         1         24m
webtraining-6677c8b95f   2         2         2         13m
webtraining-8688bf8894   0         0         0         24m
```

What if you find that your new version is a complete disaster?  Well lucky for you, that old version is still around.  Simply roll back the deployment.  Roll back the deployment and with magic the replicasets will be reversed and if you watch your continuous curl you will see the version number roll back to one.

Run the command:
```
kubectl rollout undo deployment/webtraining
```

Output:

```
deployment "webtraining" rolled back
Daniels-MBP:kubernetes dlohin$ kubectl get rs
NAME                     DESIRED   CURRENT   READY     AGE
dbtraining-77c7cf7cc6    1         1         1         31m
webtraining-6677c8b95f   0         0         0         20m
webtraining-8688bf8894   2         2         2         31m
```


Hopefully this gives you and idea of the power of Kubernetes.  One issue that we have at this point is that our MySQL database data will be lost when we destroy the container.  There are a number of ways to solve this issue using volumes and a storage provider but we didn't configure that in this lab.  Keeping software separate from data is a central tenant to Kubernetes.
