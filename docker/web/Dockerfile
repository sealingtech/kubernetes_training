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
    php-pdo \
    php-gd \
    php-mysqli \
    php-mbstring 
#Delete what is currently there
RUN rm -rf /var/www/html/*
#Copy the contents inside your current directory on your local build into the container
COPY html/ /var/www/html/
#This will fix up the Apache configuration to ensure logs to to STDOUT
COPY httpd.conf /etc/httpd/conf/httpd.conf 
#We will expose two networking ports to the container
EXPOSE 80 443 
#When the container starts start up Apache as the process
CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]
