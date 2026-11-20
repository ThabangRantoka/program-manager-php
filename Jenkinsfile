
pipeline {
    agent any

    stages {
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

        stage('Success') {
            steps {
                echo 'Program Manager Jenkins pipeline completed successfully!'
            }
        }
    }
}
