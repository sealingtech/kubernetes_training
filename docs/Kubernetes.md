##Deploy Database Pod
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
  #This spec applies to Deployments with the label app=db
  selector:
    matchLabels:
      app: dbtraining
  template:
    metadata:
      name: dbtraining
      labels:
        app: dbtraining
    #This spect has these containers
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
   
##Deploy Database Service      
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

##Deploy configuration Map
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


##Deploy Web Pod

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

##Deploy web Service

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

##Configure Ingress

```
apiVersion: extensions/v1beta1
kind: Ingress
metadata:
  name: web-service-ingress
  namespace: default
  annotations:
    kubernetes.io/ingress.class: traefik
spec:
  rules:
  - host: test.lohin.lan
    http:
      paths:
      - backend:
          serviceName: web-service
          servicePort: 80
```