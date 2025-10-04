from django.contrib import admin
from .models import Profile, Match, Message, Reward, Event, CoinPurchase

admin.site.register(Profile)
admin.site.register(Match)
admin.site.register(Message)
admin.site.register(Reward)
admin.site.register(Event)
admin.site.register(CoinPurchase)