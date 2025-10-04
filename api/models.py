from django.contrib.auth.models import User
from django.db import models

class Profile(models.Model):
    user = models.OneToOneField(User, on_delete=models.CASCADE)
    bio = models.TextField(blank=True)
    location = models.CharField(max_length=100, blank=True)
    birthdate = models.DateField(null=True, blank=True)
    gender = models.CharField(max_length=20, blank=True)
    interested_in = models.CharField(max_length=20, blank=True)
    photo = models.ImageField(upload_to="profiles/", blank=True, null=True)
    coins = models.PositiveIntegerField(default=0)
    tier = models.CharField(max_length=20, default="Bronze")

    def __str__(self):
        return self.user.username

class Match(models.Model):
    user1 = models.ForeignKey(User, related_name="matches_initiated", on_delete=models.CASCADE)
    user2 = models.ForeignKey(User, related_name="matches_received", on_delete=models.CASCADE)
    matched_on = models.DateTimeField(auto_now_add=True)

class Message(models.Model):
    match = models.ForeignKey(Match, on_delete=models.CASCADE)
    sender = models.ForeignKey(User, on_delete=models.CASCADE)
    text = models.TextField()
    sent_at = models.DateTimeField(auto_now_add=True)

class Reward(models.Model):
    name = models.CharField(max_length=100)
    description = models.TextField()
    coin_cost = models.PositiveIntegerField()
    tier_required = models.CharField(max_length=20, default="Bronze")

class Event(models.Model):
    title = models.CharField(max_length=100)
    description = models.TextField()
    event_date = models.DateTimeField()
    reward = models.ForeignKey(Reward, on_delete=models.SET_NULL, null=True, blank=True)
    created_by = models.ForeignKey(User, on_delete=models.CASCADE)

class CoinPurchase(models.Model):
    user = models.ForeignKey(User, on_delete=models.CASCADE)
    amount = models.PositiveIntegerField()
    purchased_at = models.DateTimeField(auto_now_add=True)