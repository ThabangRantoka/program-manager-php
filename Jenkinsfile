
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
            }
        }
        stage('Success') {
            steps {
                echo 'Program Manager Jenkins pipeline completed successfully!'
            }
        }
    }
}



