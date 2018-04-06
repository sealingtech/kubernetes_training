# Installing Kubernetes
This guide will step you through the process of configuring a single node Kubernetes cluster and installing Calico as the CNI and Traefik as the Ingress controller.


## Installing Docker and Kubernetes (STECH class, skip this section!!!)

NOTE!!!!  I didn't want to kill the virtual cluster with everyone installing all at once, so this part has been done for you!!!!  Don't run it again!  Start at the initializing cluster section.  This is here if you want to do it again on your own.

General configuration to meet requirements by Kubernetes.

Run the command:

```
#You can make it work with Selinux, but needs more work.  Going to disable it for this class
setenforce 0
sed -i s/SELINUX=enforcing/SELINUX=disabled/ /etc/sysconfig/ selinux /etc/selinux/config
#Kubernetes does not support swap.  With it enabled it can mess up scheduling decisions.
sed -i /swap/d /etc/fstab
#Kubernetes heavily uses IPtables, so disable firewalld so they don't interfere.
systemctl disable firewalld
echo "net.bridge.bridge-nf-call-ip6tables = 1
net.bridge.bridge-nf-call-iptables = 1
net.ipv4.ip_forward=1" > /etc/sysctl.conf
reboot
```

Install and enable Docker.

Run the command:

```
yum install -y docker
systemctl enable docker && systemctl start docker
```


Configure the Kubernetes repository and install Kubeadm which will set up the Kubernetes cluster and kubectl which is the client to manage Kubernetes.

Run the command:

```
cat <<EOF > /etc/yum.repos.d/kubernetes.repo
[kubernetes]
name=Kubernetes
baseurl=https://packages.cloud.google.com/yum/repos/kubernetes-el7-\$basearch
enabled=1
gpgcheck=1
repo_gpgcheck=1
gpgkey=https://packages.cloud.google.com/yum/doc/yum-key.gpg https://packages.cloud.google.com/yum/doc/rpm-package-key.gpg
EOF

yum install -y kubelet kubeadm kubectl
systemctl enable kubelet && systemctl start kubelet
```


## Initializing the Cluster
Initialize the cluster and choose a network range for pods to live in.  This range is only routable in the host, but shouldn't overlap with anything you would expect your cluster to communicate with.  This can take a few minutes.

Run the command:

```
kubeadm init --pod-network-cidr=192.168.0.0/16
```

Kubeadm helpfully gives you the commands to run next.  This is configuring the keys for kubectl.  You can actually copy this to any system such as your desktop and run it from there so you don't need to SSH into your cluster.  A kubeadm join command is also displayed which is how you would join more minion nodes.

Run the command:

```
mkdir -p $HOME/.kube
sudo cp -i /etc/kubernetes/admin.conf $HOME/.kube/config
sudo chown $(id -u):$(id -g) $HOME/.kube/config
```

You have a cluster!  Lets look at the pods that were deployed inside of the kube-system namespace!  Kube-system is the namespace for objects which help control and maintain the cluster.  Watch will automatically update your screen when changes occur.

Run the command:

```
kubectl get pods --namespace=kube-system --watch
```

Output (Note it may take a minute to look like this):

```
NAME                                READY     STATUS    RESTARTS   AGE
etcd-kube-test                      1/1       Running   0          3m
kube-apiserver-kube-test            1/1       Running   0          3m
kube-controller-manager-kube-test   1/1       Running   0          3m
kube-dns-86f4d74b45-qmxcj           0/3       Pending   0          4m
kube-proxy-2ttjw                    1/1       Running   0          4m
kube-scheduler-kube-test            1/1       Running   0          3mvvkvvfdbnc
```

Press ctrl-c to break from this screen.

After about a minute all the pods will be deployed except for DNS, why is DNS not working?  

Let's look at the events to get an idea why it is failing.

Run the command:

```
kubectl describe pods <name of kubedns pod> --namespace=kube-system
```

Output:

```
Events:
  Type     Reason            Age               From               Message
  ----     ------            ----              ----               -------
  Warning  FailedScheduling  2s (x18 over 4m)  default-scheduler  0/1 nodes are available: 1 node(s) were not ready.
```

Lets look at the logs.

```
tail /var/log/messages
```

Output will have a message like this:

```
Apr  6 08:53:41 student1 kubelet: E0406 08:53:41.545685    3804 kubelet.go:2125] Container runtime network not ready: NetworkReady=false reason:NetworkPluginNotReady message:docker: network plugin is not ready: cni config uninitialized
```


We still haven't deployed our Container Networking Interface (CNI).  The kubelet will continue to retry deployment and fail until this has been resolved.


Installing a CNI is pretty easy though!  We can simply apply the configuration straight from the Calico website.

Run the command:

```
kubectl apply -f https://docs.projectcalico.org/v2.6/getting-started/kubernetes/installation/hosted/kubeadm/1.6/calico.yaml
```


Next we need to configure Ingress.  This is how traffic will get into our cluster from the outside world.

Run the command:

```
kubectl apply -f https://raw.githubusercontent.com/containous/traefik/master/examples/k8s/traefik-rbac.yaml
kubectl apply -f https://raw.githubusercontent.com/containous/traefik/master/examples/k8s/traefik-ds.yaml
```

After about a minute, your Kube-dns should switch to running with 3/3 containers deployed.

Run the command:

```
kubectl get pods --namespace=kube-system --watch
```

Final Output:

```
NAME                                              READY     STATUS    RESTARTS   AGE
calico-etcd-957w2                                 1/1       Running   0          15h
calico-kube-controllers-5cc6fcf4d9-t5rmw          1/1       Running   0          15h
calico-node-x9579                                 2/2       Running   0          15h
etcd-student2.kubernetes.lab                      1/1       Running   0          15h
kube-apiserver-student2.kubernetes.lab            1/1       Running   0          15h
kube-controller-manager-student2.kubernetes.lab   1/1       Running   0          15h
kube-dns-86f4d74b45-2w8lp                         3/3       Running   0          15h
kube-proxy-47v46                                  1/1       Running   0          15h
kube-scheduler-student2.kubernetes.lab            1/1       Running   0          15h
traefik-ingress-controller-nc5s5                  1/1       Running   0          15h
```

Make sure all are running before continuing.  If you want to get more information about a specific pods you can use the describe and logs feature on the pod to get more information.

Run the command:
```
kubectl describe --namespace=kube-system <name of pod>
kubectl logs --namespace=kube-system <name of pod>
```


Kubernetes by default will not deploy user deployed containers to the master server.  The master server is reserved for managing the cluster.  This is an issue to us when we only plan on running a single node.  To remove this limitation:

Run the command:

```
kubectl taint nodes --all node-role.kubernetes.io/master-
```