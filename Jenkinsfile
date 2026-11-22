
pipeline {
    agent any

    tools {
        nodejs 'NodeJS-22'
    }

    stages {
        stage('Check NodeJS') {
            steps {
                sh 'node --version'
                sh 'npm --version'
            }
        }

        stage('Checkout') {
            steps {
                echo 'Checking out Program Manager project...'
                checkout scm
            }
        }

        stage('Verify Project') {
            steps {
                echo 'Checking project files...'
                sh '''
                    ls -la
                    test -f compose.yaml
                    test -f Dockerfile.php
                    test -d program-manager-angular
                '''
            }
        }

        stage('Build Angular') {
            steps {
                dir('program-manager-angular') {
                    sh 'npm ci'
                    sh 'npm run build -- --configuration production --base-href /app/'
                }

                stash name: 'project-files',
                      includes: '**/*', excludes: '**/node_modules/**,**/.git/**', useDefaultExcludes: false
            }
        }

        stage('Build Docker Images') {
            agent {
                label 'docker'
            }

            steps {
                unstash 'project-files'

                echo 'Building PHP backend Docker image...'
                sh 'docker build -t program-manager-backend:latest -f Dockerfile.php .'

                echo 'Building Angular frontend Docker image...'
                sh 'docker build -t program-manager-frontend:latest ./program-manager-angular'
            }
        }

        stage('Deploy Program Manager') {
            agent {
                label 'docker'
            }

            steps {
                echo 'Deploying PHP and Angular...'

                sh '''
                    cd /deployment
                    docker compose -p program-manager up -d --no-deps --no-build --force-recreate php angular
                '''

                echo 'Deployment completed!'
            }
        }
        stage('Success') {
            steps {
                echo 'Program Manager Jenkins CI/CD pipeline completed successfully!'
            }
        }
    }
}



