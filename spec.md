# **Preliminary Spec**

I consider this document a first attempt at defining the structure of the coding portion
of the GISP.

## **A Theoretical Aside**

Just making sure I understand the theoretical model correctly. Please correct any incorrect interpretation below.

The prose object model is a way of representing a legal document as a node on a graph (in the mathematical sense of the word). The document is defined by other nodes it references on a graph (this abstraction is described in Gabriel’s Monads, and is the abstraction that inspired PageRank as well). The cool thing about the prefix system is that *it provides a stronger differentiation of a node’s worldview*. Not only is a node’s view affected by its location in the graph (aka my perspective is shaped by location on earth. If I’m in Paris, I see the Eiffel Tower, if I’m in NYC, I see the Empire State), but also that prefixing provides a *different ontology*. Not only does the node have a unique view into the world – it has its *own unique world* that only it inhabits. A node’s world is defined by its **location in the graph** (its edges, and their edges, and their edges...) and **its prefixes**. Let me continue the geographic analogy, except in this case I will add the concept of prefixing back in. Consider again two nodes – one Parisian, one New Yorker. Not only are their perspectives different spatially (one sees Paris, one sees New York), but they literally live in different worlds. The Parisian operates with a certain set of prefixes (French, Parisian, etc) that defines how its perspective. Same graph, same objects, but different names. The metaphor may be forced, but I believe I got my point across.

With that philosophical aside over with, I will now describe how I believe we should design the algorithm. Note that I have not yet looked at the Perl implementation. I don't know the language and also appreciate a fresh start, but I'd be happy to refer to it later if you guys believe it would be helpful. I will begin by attempting to implement an abstract version of the model (No I/O, just pure data structures and functions operating on those data structures).

## **The Spec**

### **Defining the Structure**

I propose that we begin by defining a set of function contracts that defines a Prose Object Model in the abstract. Each node shall be, in this document, hereafter referred to as a **Document** (in the spirit of the contracts as artificats doc you guys linked).

Each Document contains a value (a list of Tokens, previously defined as the Model.root) and a link map, which maps keys to lists of tokens (in this way, each value in the edge map can be seen as a document in and of itself). Tokens can be a direct reference to a value or a reference to a yet unseen variable (hereafter refered to as "hanging tokens"). For ease of coding, I have broken out direct references into literal and linked references. Literal references refer to values (int, string, etc), whereas linked references refer to pointers to other 'complex' documents (the difference is akin to the difference between a primitive and an object in Java). Note that our first implementation will consider hanging tokens in the abstract (as a *reference to some value elsewhere in the graph*, not a string enclosed in braces), and values in the same way (not as strings or filepaths etc, but *pointers to another Document*. 

Consider the node that contains “my age is {jake.name}. Here is a full definition of my university: *pointer to the document my_university*” its value will contain, in order:

* a literal token that points to “my age is “
* a hanging token that contains a reference at 'jake.name' to be matched at render time (if this reference to this link cannot be found, it can be considered a reference to a string ‘{jake.name}’)
* another literal token ". Here is a full definition of my university: "
* and a linked token pointing to the document my_university (which is also fully expanded at render time).

### **A High Level Overview**

Rendering a document recursively renders each token in a document's value.

When a literal token is referenced, its value is rendered immediately. When a linked token is referenced, its value is rendered by recursively calling 'render_document' on the linked document. Now for the hardest part: when a hanging token is referenced, a search commences. It ends when it finds a matching key or exhausts the available subgraph and knows that there is no matching bound token at any level of prefixing (in which case it renders the key as is). If a matching key is found, its corresponding value is also expanded with render_document before being returned.

Our implementation consists of two core operations on three data structures to be further described below.

Nouns: **Document**, **Token** (assumes the existence of List and Map)

Verbs: **Render_document**, **Deference**, **Search_for**

(Ignore that in a functional world, verbs are also noun)

A few things to consider:

I am approaching currently approaching the object model as if it can't have cycles.

Also note that the label of any given key is path dependent. For example, in the graph:

--------a--------  
-------/--\\-------  
------b---c------  
-------\\---/------  
--------d--------

the keys in 'd' can have two names - those that contain prefixes from 'b', and those that contain prefixes from 'c'. In this current implementation, I will be able to cover all possible paths through the graph. Later, when I attempt to handle cycles, I may not be able to cover all paths (it remains to be seen if I can).

Future iterations of the algorithm will be able to handle cycles. I am planning to attempt the approach described [here](https://futtetennismo.me/posts/algorithms-and-data-structures/2017-12-08-functional-graphs.html). The link describes two ways to construct functional algorithms on graphs, a generate/prune approach and an approach using a new represenation (inductive graphs) and pattern matching. The inductive representation is better described in the article above (search for "Enter Inductive Graphs").

#### **Nouns: Data Structures**

Our first implementation will operate only on the level of these entities:

* A **Document**, which has only one type. It contains a value (a list of Tokens (described below))m and a link_map of type Map<String, [Token]>, or differently conceptualized, Map<String, Document([Tokens], {})>. Constructer: document(value :: [Token], link_map :: Map<String, [Token]>)
* A **Token**, which has three types:
    1. **Literal**, which is a reference to an immediate value (int, string, bool, etc). Constructer: token(value :: Object)
    2. **Hanging**, is a link to an unknown value. This is what was previously referred to as a 'variable'. Constructer: token(variable :: String)
    3. **Linked**, which is a reference to a another Document. Constructer: token(reference :: String? Not sure how I plan to model a reference... Maybe any data structure that can be passed to a dereference function?)

I would also like to consider the idea of a Universe, which is defined by all possible combination of prefixed to totally deprefixed (empty string) values. **Note that the algorithm as currently described performs a greedy search, choosing to inhabit the first valid Universe it encounters on each execution of render_key.** Any formal verification / queries we answer can only refer to the items within the universe (although it can refer to how the universe can behave even with arbitrary input).

#### **Verbs: Functions**

* **Render_document**

  A Document is rendered by recursively dereferencing the next token in value and adding that to the return value of render_document on the rest of the token list.
  
  Base case: When tokens is empty, it returns the empty string

* **Dereference_token**

  Hanging Tokens can be considred as references to currently unknown tokens to be discovered at render time by the algorithm sketched below:

  1. Call scan_options_in_current_document to discover the best (if any) matching key in the current document. If a fully prefixed key is found, you can return, since all matches in the subtree (I know its a graph but we can consider a model of it as a tree of the path being walked) rank below that match.
  2. If we did not find a fully prefixed match, recur on each value in your map that is a linked_token.
  3. Use the comparison function (described below) to choose the highest ranking matched value and then return that.

* **Scan_options_in_current_document**
  1. Loop through the keys in the document, deprefixing until you find a valid match, which you return.

* **Comparison_function**
  1. Consider two potential matches, x and y. If x has a longer prefix than y, return x. If they have the same prefix length, return the one that was discovered first. If neither of the previous two conditions hold, then y has a longer prefix than x, so return it.

### Before the pseudocode: A (painfully insufficient) Primer on Functional Programming

For someone who is not familiar with functional programming, read the next paragraph. For those who are, feel free to skip to the next header (A More in Depth Definition (with Pseudocode)). The pseudocode syntax isn't exactly Haskell, but its close enough that you should be able to make sense of it (its taken,in part, from pyret, the language taught in CS19 at Brown).

NOTE: I am nowhere near an expert in this, so if I am unclear or you are still lost / want to learn more, please follow your curiosity on Google, as there are plenty of great tutorials out there. You can find a tutorial that uses Haskell, a functional language, [here](http://learnyouahaskell.com/chapters).

A quick introduction to the syntax used below: I wrote the pseudocode in a functional manner using recursion. I assumed no mutable values (any mutation is assumed to be a function that takes in the original structure and outputs the new one). The main syntax that may be confusing is the cases block (similar to guards / pattern matching in Haskell). The cases block can be seen as a special type of if statement, except it often depends solely on the structure of the data. The program matches the first 'pattern' it can find, and then executes that block.

For example, imagine counting items in a list. The list can be either empty (a pattern, 'mt') or still have values (also a pattern, here referred to as cons(head, rest)). If it still has values left, return 1 + the same function called on the rest of the list. If not, then you return the current counter. In the imperative approach, the list structure does not help you solve the problem, so we must define the solution in the method, whereas in the functional approach, the solution falls nicely out of the structure of the data.

Here's the example described above:

```python
#Imperatively
def list_length(list):
  '''
  Calculates the length of a list.
  '''
  length = 0
  for i in list:
    length += 1


#Functionally

#the data structure
data List:
  | cons(head :: Object, rest :: List)
  | mt

#the function
def list_length(list):
  '''
  Calculates the length of a list.
  '''
  cases(List):
    | populated =>
      return 1 + list_length(rest(list))
    | mt => return 0
```

Another example data structures would be:

```python
data Tree:
  | populated(value :: Object, left_child :: Tree, right_child :: Tree)
  | leaf(value :: Object)
```

The aforementioned [Haskell tutorial](http://learnyouahaskell.com/chapters) that I am exploring succinctly said (emphasis mine), "Recursion is important to [functional programming] because unlike [in imperative programming], you do computations in [functional programming] by declaring **what something is** instead of declaring **how you get it**." If you are interested in diving more deeply into functional concepts, I would highly recommend following the link above. [Here](http://learnyouahaskell.com/chapters) it is again.

Recursion is a central concept in functional programming. Each recursive function can be seen as performing the same computation on subsets of the structure and then combining all the values to calculate the final answer. The structure of the data is therefore very important and can also end up driving the algorithm. I haven't programmed functionally in a while (since freshman year), but I remember that Geoffrey mentioned Haskell as an option, and I believe that the core algorithm would be well suited to a functional approach. I've been reading through some Haskell tutorials and believe I have enough of a grasp of the language to code an MVP of the core algorithm in the next step of this process. Thoughts would be appreciated on this point, because starting in Haskell may force the others to learn it as well, which could be burdensome. Either way, beginning with a functional approach will really help me understand the problem, so even if we switch to python, we can use the structure defined here.

### **A More in Depth Definition (with Pseudocode)**

#### **Data Structures (with Pseudocode)**

```python
data Document:
  '''
  Represents a Node on the Prose Object Graph. Note that the "value" field is
  equivalent to Model.root in the previous implementation.

  Types
  -----
  linked: A node that has outgoing links.
  '''
  | linked(value :: [Token], link_map :: {key1 : [Token], ...})

data Token:
  '''
  Represents a Token (a value) on the Prose Object Graph. The closest analogue
  in the previous implementation was a variable. I expanded its definition to include
  normal strings (it makes the recursion easier).

  Types
  -----
  literal: A token that references a literal value (string, int, etc).
  
  linked: A token that is a direct reference to another document.

  hanging: A token that has a reference to a value in a key/value pair
    somewhere in the graph.

  mt: A token with no value. Useful for deprefixing.
  '''
  # I need to consider how to implement this with extensibility to scripts in mind
  # This needs to be able to handle code as well as text...
  # a literal value (int, bool, string, etc)
  | literal(value :: Object)
  # a reference to a document
  | linked(value :: String)
  # a reference to a token somewhere else
  | hanging(value :: String)
  # an empty token. Has no value
  | mt
```

#### **Functions (with Pseudocode)**

```python


def render_document(document, prefixes):
  '''
  Render a document. Note that document.value is my version of root.map.
  Also note that prefixes is stored in recent to least recent, meaning you need
  to reverse when composing it into a single string. The initial execution of
  this function would look like:
  render_document(document, [])

  Parameters
  ----------
  document: the document to render
  prefixes: any prefixes to begin rendering with

  Returns
  -------
  The string representing the rendered document.
  '''
  tokens = document.value
  #dereference the next variable
  cases(List) tokens:
    | [] => return '' #return if tokens is an empty list. This is the base case
    | (head, rest) =>
      rest_of_document = document(rest, token_map)
      cases(Token) head:
        | literal =>
          #if the token is literal, then we return its value + the rest of the rendered map
          return head.value + render_document(rest_of_document, prefixes)
        | hanging =>
          #add the necessary prefixes to the variable
          head = fold(reverse(prefixes), lambda x, y: x + y) + head
          prefixes, dereferenced, call_number, matched_document =
            dereference_token(document, next_variable, prefixes, 0)
          # the dereferenced variable may itself contain a reference which we must render
          # we create a modified version where its value to render is the dereferenced value.
          modified_document = document(dereferenced, document.link_map)
          # a tuple (prefixes, best_dereferenced_value, call_number)
          output = render_document(modified_document, prefixes)
          matched = output[1]
          # leave the reference in if no referenced value is found
          cases(match):
            | mt => return head.value + render_document(rest_of_document, prefixes)
            | value => return value + render_document(rest_of_document, prefixes)
        | linked =>
          # if it's linked we want to render it as a new document, but with the
          # existing prefixes
          output = render_document(head.reference, prefixes)
          matched = output[1]
          cases(match):
            | mt => return head.value + render_document(rest_of_document, prefixes)
            | otherwise => return matched.value + render_document(rest_of_document, prefixes)

def dereference_token(document, token, prefixes, call_number):
  '''
  Dereferences a token.

  Parameters
  ----------
  document: the document we are dereferencing in
  token: the token to be dereferenced
  prefixes: the prefixes to be added during the dereferencing
  call_number: the depth of the current call in the recursive call stack

  Returns
  -------
  (prefixes, best_dereferenced_value, call_number): The prefixes that were used
    to match, the value that was matched, and the call_number.
  '''
  # return the best matched key and the prefixes that it was matched with
  (prefixes, best_choice) = scan_options_in_current_doc(link_map, token, prefixes)
  if len(prefixes) == depth:
    # if full prefixed answer no need to recur as everything in the subtree
    # is of lower priority in the ordering
    return (prefixes, best_choice, call_number, document)
  else:
    best_list = [(mt, mt, call_number)]
    # get all keys that are a reference to another document
    # NOTE: This does not capture references to documents that are within
    # larger lists of token. For example, it misses key="Here is me!" + [/path/to/myself]
    neighbors == [(k, v) for k, v in link_map.keys, link_map.value where k.is_linked]
    # recur on each neighbor
    for key, neighbor in neighbors:
      new_prefixes = cons(key, prefixes)
      best_list = cons(dereference_token(neighbor, token, new_prefixes, call_number + 1),
        best_list)
    # fold to return the option with the best prefix length. If no matches were
    # found, it will return (mt, mt, call_number).
    return fold(best_list, comparison_function(x, y))

def scan_options_in_current_doc(link_map, token, prefixes):
  '''
  Finds the best match in the current document. Recursively deprefixes until
  a match is found.

  Parameters
  ----------
  link_map: The key/value map of the current document being searched
  token: The token to be dereferenced
  prefixes: The prefixes up to be used while matching.

  Returns
  -------
  (prefixes, dereferenced_value): The prefixes that were used in the matching and
    the dereferenced value.
  '''
  squashed_prefixes = fold(reverse(prefixes), lambda x, y: x + y)
  # prefixed token to match
  token = squashed_prefixes + token
  # create list of prefixed keys
  pref_keys = map(link_map.keys, lambda x: squashed_prefixes + x)
  # dictionary matching prefixed key to original key
  k_to_pk = {p_k : p for p_k, k in link_map.keys, pref_keys}
  
  if token is in pref_keys:
    #  we matched!
    key = k_to_pk[token]
    return (prefixes, link_map[key])
  else:
    # there was no match
    # recur if there are still prefixes left. Else return mt.
    cases(List) prefixes:
      | mt =>
        return(mt, mt)
      | (head, rest) =>
        return scan_options_in_current_doc(link_map, token, rest)

def comparison_function(x, y):
  '''
  Compares x and y. Returns longest prefix. If tiebreaker, returns the earlier match.
  Remember that x and y are tuples (prefixes, best_choice, call_number). Also note
  that this works even if one or both of x and y are of the form (mt, mt, call_number).

  Parameters
  ----------
  x, y: tuples of the form (prefixes, chosen_dereference_value, call_number) to be compared.

  Returns
  -------
  The value with higher priority (less deprefixed, or if even prefix length, earlier called)
  '''
  x_prefix, x_choice, x_call_number = x[0], x[1], x[2]
  y_prefix, y_choice, y_call_number = y[0], y[1], y[2]
  # If x is less deprefixed then y, return x
  if len(x_prefix) > len(y_prefix):
    return x
  # If y is less deprefixed then x, return y
  elif len(x_prefix) < len(y_prefix)
    return y
  # else there is a tie on prefix length so return the value seen earlier
  else:
    if x_call_number < y_call_number:
      return x
    else:
      return y
```

### Moving Forward: a Roadmap

Firstly, we should establish a fresh repo. We can definitely add previous work (Perl code, Geoffrey's work) as seperate branches, but I think it would be nice to have a clean canvas (in terms of Git history) to begin with (on second thought, I'm fairly indifferent to this point... up to you guys). We should also discuss the particulars of how we develop in the repo and merge code to master - Test Driven Development, a Checkstyle, and the expected branching / commit message / pull request behavior. Then we should agree upon a more detailed long term roadmap. The one I define below has an incredibly large scope (I don't expect to complete all of it, especially within a semester), but each step is a subset of the last and a totally functional project in and of itself. However, we should aim to be developing with future steps in mind, even if we don't believe we will complete them all. Currently I propose:

1. Choose a language for the first attempt at coding the algorithm (I'm leaning towards Haskell)
2. Finish the algorithm (including tests). Note that this step does not include any complex I/O like reading and writing to files - it will just be predefined structures held in memory.
3. Provide an implementation of the algorithm. I think the filesystem based approach from the original attempt would be a good place to start.
4. Design an interface. Write a simple front end (I'm leaning towards React), wrap the code from step 3 in a server. Test on localhost.
5. Host the code and interface. Possibly consider containerizing it and deploying it as a service using Docker, Kubernetes, and AWS/Google Cloud/IPFS.
6. Extend functionality to include version control. This means Git!
7. Second round of UX - a great goal (obviously a stretch) is to emulate the experience of google docs. This means Git + concurrent editing of the same working directory!
8. Add a Query language on top of the Prose Object Model. Just like SQL opened up a whole new universe of questions (arbitrary queries on relational data) we can open up a whole universe of questions (how does this mathematical model of the graph *behave when something happens*... what are the formally provable *bounds on the graph's behavior*). If Acme goes bankrupt, what happens to my bond holdings? If England wins the game, what happens to my account balance? Can I go bankrupt if at least 70% of my debtors pay me back? Etc. We should take inspiration from other declarative language (ie SQL). We should consider exactly what mathematical proofs we can provide about the behavior of the graph aka **formal verification** (very large stretch, would take a lot of learning). We should also consider this when implementing the algorithm. Any desire to **reason formally about the Prose Object Model** may require a **functional approach**.
9. Add integration functionality to arbitrary ledgers. Any ledger that fulfills this API should be able to implement and interact with the Prose Object Model. Examples of ledgers: A SQL database, VISA, Ethereum, Bitcoin. NOTE: can we allow for the option of the prose object model being run in a decentralized fashion? It feels in many ways like smart contracts to me.
10. Add specific implementations of these ledger APIs. I'm thinking we first do a SQL database implementation and an Ethereum implementation.