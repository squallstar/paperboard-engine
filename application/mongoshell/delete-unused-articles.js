var feeds = db.feeds.find({}, {'_id': 1, 'title': 1}).limit(1000);

while (feeds.hasNext())
{
  var feed = feeds.next();
  var feed_id = feed['_id'].str;

  if (db.category_children.count({'feed_id': feed_id}) == 0)
  {
      db.articles.remove({source: feed_id});
      db.feeds.remove({_id: feed['_id']}, {justOne: true});
  }
}