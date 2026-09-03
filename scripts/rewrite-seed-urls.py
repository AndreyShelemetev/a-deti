"""Rewrites image URLs in prisma/seed-data/seed.json using the map produced by convert-images.mjs."""
import json, sys, re
seed_path, map_path = sys.argv[1], sys.argv[2]
m = json.load(open(map_path))
raw = open(seed_path, encoding="utf8").read()
count = 0
def repl(match):
    global count
    url = match.group(0)
    if url in m and m[url] != url:
        count += 1
        return m[url]
    return url
raw = re.sub(r"/uploads/[^\s\"'<>()]+", repl, raw)
data = json.loads(raw)
# drop references to files we could not download (keep placeholders instead of broken images)
missing = 0
def check(url):
    global missing
    if url and url.startswith("/uploads/") and url not in m.values() and url not in m:
        missing += 1
        return None
    return url
for t in data["tours"]:
    t["cover"] = check(t["cover"])
    t["images"] = [u for u in t["images"] if check(u)]
    t["documents"] = [d for d in t["documents"] if check(d["url"])]
for p in data["posts"]:
    p["image"] = check(p["image"])
for s in data["slides"]:
    s["image"] = check(s["image"])
data["slides"] = [s for s in data["slides"] if s["image"]]
for f in data["features"]:
    f["icon"] = check(f["icon"])
data["partners"] = [p for p in data["partners"] if check(p["logo"])]
for p in data["reviewPlatforms"]:
    p["logo"] = check(p["logo"])
json.dump(data, open(seed_path, "w", encoding="utf8"), ensure_ascii=False, indent=1)
print(f"rewritten urls: {count}, dropped missing: {missing}")
