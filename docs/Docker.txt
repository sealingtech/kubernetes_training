# Building Your Work Environment 
## Install Docker
Note: The commands in the guide will be Debian based (Ubuntu).  However, docker is not limited to just this distribution and can be downloaded on most other distributions, as well as MAC OS, and Windows 10 platform. 

1. First we will update our package list and install docker community edition.
```
sudo apt-get update
sudo apt-get install docker-ce 
```

2. Start the Docker service
```
Sudo service docker start 
```

3. Start the first container.  There is a simple hello world container on Docker hub that we can start and test.
```
docker run hello-world
```
Output should be:
```
Unable to find image 'hello-world:latest' locally
latest: Pulling from library/hello-world
ca4f61b1923c: Pull complete
Digest: sha256:97ce6fa4b6cdc0790cda65fe7290b74cfebd9fa0c9b8c38e979330d547d22ce1
Status: Downloaded newer image for hello-world:latest

Hello from Docker!
This message shows that your installation appears to be working correctly.

To generate this message, Docker took the following steps:
 1. The Docker client contacted the Docker daemon.
 2. The Docker daemon pulled the "hello-world" image from the Docker Hub.
    (amd64)
 3. The Docker daemon created a new container from that image which runs the
    executable that produces the output you are currently reading.
 4. The Docker daemon streamed that output to the Docker client, which sent it
    to your terminal.

To try something more ambitious, you can run an Ubuntu container with:
 $ docker run -it ubuntu bash

Share images, automate workflows, and more with a free Docker ID:
 https://cloud.docker.com/

For more examples and ideas, visit:
 https://docs.docker.com/engine/userguide/
```
 
 
#Creating our first Docker container

We have used a default container from Docker hub, but lets make our own container.  We will use Centos for our package source and start from there.  We will then install a number of packages inside of it and then expose port 80 and 443.  Finally we will specify the executable to start when the container comes up.  
 
1. Create a file called Dockerfile with the following contents (note capital "D" is important)

```
#Start with Centos for the package source
FROM centos:centos7 
#Who maintains the image (could be your email)
MAINTAINER Stech Training 
#Best practice to always update
RUN yum -y update 
#Install Apache
RUN yum -y install httpd 
#Install PHP and some addons
RUN yum -y install php \
	Php-mysql \
	Php-pdo \
	Php-gd \
	Php-mbstring 
#Delete what is currently there
RUN rm -rf /var/www/html/*
#Copy the contents inside your current directory on your local build into the container
COPY html/ /var/www/html/
#We will expose two networking ports to the container
EXPOSE 80 443 
#When the container starts start up Apache as the process
CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]
```

2. Build the container.  This will first download the Centos image (if it hasn't been downloaded already) and then begin executing then begin executing the commands in your Dockerfile.  When this is done it will save off an image into your local Docker repository called stech/apache.  We will then view our image repository.

```
docker build -t stech/apache . 
docker image ls
```

3. Start up the container.  The arguments are as follows
  + -t Give the container a tty (a terminal) so you can execute a shell
  + -d Detached, run in the background
  + --name give the container the name apache
  + -p Map port 80 on the local host port to the container port 80.  Your local host will listen on port 80 and forward all requests to your Docker container also listening on port 80.  When we move the Docker container to Kubernetes we will use  more robust networking options, but this works for testing.

```
docker run -td --name apache -p 80:80 stech/apache 
```

4. The containers is now running in the background, you can access the data

```
sudo docker exec -it apache bash  
```


