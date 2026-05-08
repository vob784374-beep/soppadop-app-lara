output "db_endpoint" {
  value = aws_db_instance.app.address
}

output "redis_endpoint" {
  value = aws_elasticache_cluster.redis.configuration_endpoint
}
