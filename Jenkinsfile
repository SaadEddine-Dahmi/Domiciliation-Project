pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
    }

    stages {
        stage('Verify Docker') {
            steps {
                sh 'docker --version'
                sh 'docker compose version'
            }
        }

        stage('Build Backend Test Image') {
            steps {
                sh 'docker compose -f docker-compose.ci.yml build backend-test'
            }
        }

        stage('Start Test Database') {
            steps {
                sh 'docker compose -f docker-compose.ci.yml up -d postgres-test'
            }
        }

        stage('Run Laravel Tests') {
            steps {
                sh 'docker compose -f docker-compose.ci.yml run --rm backend-test sh -lc "php artisan migrate:fresh --force && php artisan test"'
            }
        }

        stage('Build Production Images') {
            steps {
                sh 'docker compose build backend frontend'
            }
        }
    }

    post {
        always {
            sh 'docker compose -f docker-compose.ci.yml down -v --remove-orphans || true'
        }
    }
}
