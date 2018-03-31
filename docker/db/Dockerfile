FROM mariadb:10.3

#Set the environment variables the container uses
ENV MYSQL_ROOT_PASSWORD password12345
ENV MYSQL_DATABASE applications 
ENV MYSQL_USER docker_man
ENV MYSQL_PASSWORD docker12345 

#Copy SQL commands to create the tables needed for out web app

COPY createtable.sql /docker-entrypoint-initdb.d/





