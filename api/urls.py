from rest_framework.routers import DefaultRouter
from django.urls import path, include
from .views import (
    UserViewSet, ProfileViewSet, MatchViewSet, MessageViewSet,
    RewardViewSet, EventViewSet, CoinPurchaseViewSet
)

router = DefaultRouter()
router.register(r'users', UserViewSet)
router.register(r'profiles', ProfileViewSet)
router.register(r'matches', MatchViewSet)
router.register(r'messages', MessageViewSet)
router.register(r'rewards', RewardViewSet)
router.register(r'events', EventViewSet)
router.register(r'coinpurchases', CoinPurchaseViewSet)

urlpatterns = [
    path('', include(router.urls)),
]