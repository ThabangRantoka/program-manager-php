FROM nginx:alpine

COPY dist/program-manager-angular/browser/ /usr/share/nginx/html/app/

COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80