from django.contrib.auth.models import User
from rest_framework import viewsets, permissions
from .models import Profile, Match, Message, Reward, Event, CoinPurchase
from .serializers import (
    UserSerializer, ProfileSerializer, MatchSerializer,
    MessageSerializer, RewardSerializer, EventSerializer, CoinPurchaseSerializer
)

class UserViewSet(viewsets.ReadOnlyModelViewSet):
    queryset = User.objects.all()
    serializer_class = UserSerializer

class ProfileViewSet(viewsets.ModelViewSet):
    queryset = Profile.objects.all()
    serializer_class = ProfileSerializer

class MatchViewSet(viewsets.ModelViewSet):
    queryset = Match.objects.all()
    serializer_class = MatchSerializer

class MessageViewSet(viewsets.ModelViewSet):
    queryset = Message.objects.all()
    serializer_class = MessageSerializer

class RewardViewSet(viewsets.ModelViewSet):
    queryset = Reward.objects.all()
    serializer_class = RewardSerializer

class EventViewSet(viewsets.ModelViewSet):
    queryset = Event.objects.all()
    serializer_class = EventSerializer

class CoinPurchaseViewSet(viewsets.ModelViewSet):
    queryset = CoinPurchase.objects.all()
    serializer_class = CoinPurchaseSerializer